"""BCP Module 4: DEMO exam timetable preview, no database writes.
Inputs come from the PHP database loader, NEVER from the browser directly.
Saved weekly classes are conservatively considered occupied on matching dates.
"""
from collections import defaultdict
from datetime import date
from ortools.sat.python import cp_model
from app.exam_gap_audit import audit_zero_exam_gaps

DAYS = ('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')


def minutes(clock):
    parts = str(clock).split(':')
    if len(parts) < 2:
        raise ValueError('Invalid time: ' + str(clock))
    return int(parts[0]) * 60 + int(parts[1])


def covers(windows, day, start, end):
    has_available = any(
        w['day_of_week'] == day and 
        w['availability_status'] == 'AVAILABLE' and 
        minutes(w['start_time']) <= start and 
        end <= minutes(w['end_time'])
        for w in windows
    )
    
    has_unavailable = any(
        w['day_of_week'] == day and 
        w['availability_status'] == 'UNAVAILABLE' and 
        start < minutes(w['end_time']) and 
        minutes(w['start_time']) < end
        for w in windows
    )
    
    return has_available and not has_unavailable


def blocked(existing, key, resource_id, day, start, end):
    return any(
        int(m[key]) == resource_id and 
        m['day_of_week'] == day and 
        start < minutes(m['end_time']) and 
        minutes(m['start_time']) < end
        for m in existing if m.get(key) is not None
    )


def reject(code, message, **details):
    return {
        'success': False, 
        'status': code, 
        'message': message,
        'database_write': False, 
        **details
    }


def solve_exam(data):
    try:
        if data.get('program_code') != 'BSIT' or data.get('data_origin') != 'DEMO':
            return reject('PROGRAM_NOT_CONFIGURED', 'Only the BSIT DEMO examination policy is configured.')
            
        days = [date.fromisoformat(x) for x in data['exam_dates']]
        
        if len(days) != 3 or len(set(days)) != 3 or days != sorted(days):
            return reject('INVALID_DATES', 'Choose three distinct examination dates in ascending order.')
            
        if any(x.weekday() >= 6 for x in days):
            return reject('INVALID_DATES', 'Sunday exams are not enabled in this DEMO.')
            
        hour_start, hour_end = int(data['start_hour']), int(data['end_hour'])
        
        if (hour_start, hour_end) not in ((6, 21), (18, 21)):
            return reject('INVALID_EXAM_WINDOW', 'Select 06:00–21:00 or 18:00–21:00.')
            
        slots_per_day = hour_end - hour_start
        sections = {int(r['section_id']): r for r in data['sections']}
        rooms = {int(r['room_id']): r for r in data['rooms']}
        teachers = {int(r['teacher_id']): r for r in data['teachers']}
        exams = data['exams']
        existing = data['existing_classes']
        
        db_slots = defaultdict(set)
        for row in data['time_slots']:
            db_slots[row['day_of_week']].add((minutes(row['start_time']), minutes(row['end_time'])))
            
        room_windows = defaultdict(list)
        teacher_windows = defaultdict(list)
        
        for x in data['room_availability']:
            room_windows[int(x['room_id'])].append(x)
            
        for x in data['teacher_availability']:
            teacher_windows[int(x['teacher_id'])].append(x)
            
        if not sections or not rooms or not exams or not teachers:
            return reject('MISSING_RESOURCES', 'Sections, exams, rooms, or proctors are missing.')
            
        if len(exams) > 500:
            return reject('TOO_MANY_EXAMS', 'Demo preview supports at most 500 section-subject exams.')
            
        ids = [int(e['section_subject_id']) for e in exams]
        if len(ids) != len(set(ids)):
            return reject('DUPLICATE_EXAMS', 'Duplicate section-subject examination input.')
            
        # Same program/year: only ONE examination may happen in any hour.
        counts = defaultdict(int)
        for e in exams:
            sec = sections.get(int(e['section_id']))
            if not sec or int(sec['program_id']) != int(data['program_id']):
                return reject('BAD_SECTION', 'An exam references a missing or foreign section.')
                
            teacher_id = int(e['proctor_id'])
            if teacher_id not in teachers or int(teachers[teacher_id]['program_id']) != int(data['program_id']):
                return reject('BAD_PROCTOR', 'An exam has no eligible program-specific proctor.')
                
            counts[int(sec['year_level'])] += 1
            
        for year, count in counts.items():
            if count > 3 * slots_per_day:
                return reject(
                    'EXAM_WINDOW_TOO_SMALL',
                    f'Year {year} needs {count} non-overlapping section exams, but only {3 * slots_per_day} hours exist across three days. Select the wider exam window.',
                    year_level=year, 
                    required_exams=count, 
                    available_hours=3 * slots_per_day
                )
                
        model = cp_model.CpModel()
        by_year, by_teacher, by_subject, by_section, by_room = (defaultdict(list) for _ in range(5))
        entries = []
        
        for idx, e in enumerate(exams):
            sid = int(e['section_id'])
            sec = sections[sid]
            teacher_id = int(e['proctor_id'])
            count = int(sec['student_count'])
            eligible = []
            
            for day_idx, actual_date in enumerate(days):
                weekday = actual_date.strftime('%A')
                
                for hour in range(hour_start, hour_end):
                    start_min, end_min = 60 * hour, 60 * (hour + 1)
                    
                    if ((start_min, start_min + 30) not in db_slots[weekday] or 
                        (start_min + 30, end_min) not in db_slots[weekday]):
                        continue
                        
                    if not covers(teacher_windows[teacher_id], weekday, start_min, end_min):
                        continue
                        
                    if blocked(existing, 'teacher_id', teacher_id, weekday, start_min, end_min):
                        continue
                        
                    for room_id, room in rooms.items():
                        if int(room['capacity']) < count:
                            continue
                            
                        # Restrict department-owned classrooms, allow explicitly shared rooms.
                        if room.get('program_id') is not None and int(room['program_id']) != int(data['program_id']):
                            continue
                            
                        if not covers(room_windows[room_id], weekday, start_min, end_min):
                            continue
                            
                        if blocked(existing, 'room_id', room_id, weekday, start_min, end_min):
                            continue
                            
                        eligible.append((day_idx * slots_per_day + hour - hour_start, room_id))
                        
            if not eligible:
                return reject(
                    'NO_ELIGIBLE_EXAM_SLOT',
                    f"No available one-hour exam slot, room and subject-teacher proctor for {e['subject_code']} / section {sec['section_code']}. Choose other dates or a wider window."
                )
                
            allowed_starts = sorted(set(slot for slot, _ in eligible))
            start = model.NewIntVarFromDomain(cp_model.Domain.FromValues(allowed_starts), f'start_{idx}')
            end = model.NewIntVar(1, 3 * slots_per_day, f'end_{idx}')
            model.Add(end == start + 1)
            
            allowed_rooms = sorted(set(room for _, room in eligible))
            room_var = model.NewIntVarFromDomain(cp_model.Domain.FromValues(allowed_rooms), f'room_{idx}')
            model.AddAllowedAssignments([start, room_var], eligible)
            
            day_var = model.NewIntVar(0, 2, f'day_{idx}')
            model.AddDivisionEquality(day_var, start, slots_per_day)
            
            by_year[int(sec['year_level'])].append(start)
            by_teacher[teacher_id].append(start)
            by_subject[int(e['subject_id'])].append(start)
            by_section[sid].append(start)
            
            for room_id in allowed_rooms:
                selected = model.NewBoolVar(f'room_{idx}_{room_id}')
                model.Add(room_var == room_id).OnlyEnforceIf(selected)
                model.Add(room_var != room_id).OnlyEnforceIf(selected.Not())
                by_room[room_id].append(
                    model.NewOptionalIntervalVar(start, 1, end, selected, f'room_interval_{idx}_{room_id}')
                )
                
            entries.append({
                'exam': e, 
                'section': sec, 
                'start': start, 
                'room': room_var, 
                'day': day_var,
                'eligible': set(eligible)
            })
            
        for group in (by_year, by_teacher, by_subject, by_section):
            for slots in group.values():
                if len(slots) > 1:
                    model.AddAllDifferent(slots)
                    
        for intervals in by_room.values():
            if len(intervals) > 1:
                model.AddNoOverlap(intervals)

        # ==============================================================
        # BSIT 4TH YEAR: CLUSTER ON ONE DAY (DAY 1/2); MAJOR ON DAY 3.
        # These restrictions apply to OFFICIAL section types, not section
        # numbers or assumed one-to-one Cluster/Major student pairings.
        # Section-level and actual-student zero-gap rules remain below.
        # ==============================================================
        fourth_year_exams = defaultdict(list)
        for row in entries:
            sec = row['section']
            if int(sec['year_level']) == 4:
                fourth_year_exams[int(row['exam']['section_id'])].append(row)

        cluster_count = 0
        major_count = 0
        
        for sid, sec in sections.items():
            if int(sec['year_level']) != 4:
                continue
                
            section_type = str(sec['section_type']).upper()
            rows = fourth_year_exams[sid]
            
            if section_type == 'CLUSTER':
                # Approved BSIT 1st-semester DEMO: three Cluster exams.
                # Fail rather than silently scheduling incomplete Cluster data.
                if len(rows) != 3 or len({int(r['exam']['subject_id']) for r in rows}) != 3:
                    return reject(
                        'INVALID_FOURTH_YEAR_CLUSTER',
                        f"Fourth-year BSIT Cluster {sec['section_code']} needs exactly "
                        'three distinct examination subjects for this DEMO policy.',
                        section_id=sid, 
                        received_exams=len(rows),
                    )
                    
                cluster_day = model.NewIntVar(0, 1, f'cluster_{sid}_day_1_or_2')
                for row in rows:
                    model.Add(row['day'] == cluster_day)
                # Contiguity of these 3 exams is already a HARD rule in the
                # section-level compact_groups model below.
                cluster_count += 1
                
            elif section_type == 'MAJOR':
                if not rows:
                    return reject(
                        'MISSING_FOURTH_YEAR_MAJOR',
                        f"Fourth-year BSIT Major {sec['section_code']} has no exam.",
                        section_id=sid,
                    )
                for row in rows:
                    model.Add(row['day'] == 2)  # Day 3, zero-based index.
                major_count += 1
        # ==============================================================

        # Merge the two official section memberships for fourth-year students.
        students = data['students']
        memberships = defaultdict(set)
        
        for st in students:
            members = {int(st['home_section_id'])}
            if st.get('major_section_id') is not None:
                members.add(int(st['major_section_id']))
            for sec_id in members:
                memberships[sec_id].add(str(st['student_id']))
                
        if not students:
            return reject('STUDENTS_NOT_LOADED', 'Student memberships are required for shared-section examination checking.')
            
        # Require actual DEMO roster coverage for each section; protect against missing links.
        for sid, sec in sections.items():
            if len(memberships[sid]) != int(sec['student_count']):
                return reject(
                    'INCOMPLETE_STUDENT_MEMBERSHIP',
                    f"Section {sec['section_code']} has {len(memberships[sid])} mapped students but expects {sec['student_count']}."
                )
                
        linked_pairs = set()
        for st in students:
            if st.get('major_section_id') is not None:
                linked_pairs.add((int(st['home_section_id']), int(st['major_section_id'])))
                
        for a, b in linked_pairs:
            if a not in by_section or b not in by_section:
                return reject('INVALID_SHARED_SECTION', 'Shared student section has missing exams.')
            model.AddAllDifferent(by_section[a] + by_section[b])
            
        # Every individual student, including Cluster + Major, <= 3 exams per day.
        # Aggregate by unique section membership signature instead of 500 copies.
        groups = {
            tuple(sorted(
                {int(st['home_section_id'])} | 
                ({int(st['major_section_id'])} if st.get('major_section_id') is not None else set())
            ))
            for st in students
        }

        # HARD RULE: one physical examination room per section for the entire
        # three-day examination period, regardless of subject or exam day.
        # For fourth-year students, the linked Cluster + Major memberships are
        # treated as one student group and therefore must share that same room.
        fixed_room_sections_checked = 0
        for sid in sorted(sections):
            section_rows = [row for row in entries if int(row['exam']['section_id']) == sid]
            
            if not section_rows:
                return reject('SECTION_EXAMS_MISSING', f"Section {sections[sid]['section_code']} has no examination entries.")
                
            anchor_room = section_rows[0]['room']
            for row in section_rows[1:]:
                model.Add(row['room'] == anchor_room)
            fixed_room_sections_checked += 1

        fixed_room_student_groups_checked = 0
        for group in sorted(groups):
            group_rows = [row for row in entries if int(row['exam']['section_id']) in group]
            
            if not group_rows:
                return reject('STUDENT_EXAMS_MISSING', f'Student group {group} has no assigned exams.')
                
            anchor_room = group_rows[0]['room']
            for row in group_rows[1:]:
                model.Add(row['room'] == anchor_room)
            fixed_room_student_groups_checked += 1

        # Section-level contiguity keeps each independently printable Cluster,
        # Major and regular-section exam sheet free of vacant time as well.
        # Actual student groups are checked independently below; no assumption
        # that one Cluster always maps to a specific Major is made.
        compact_groups = sorted(groups | {(sid,) for sid in sections})
        objectives = []
        
        for group_number, group in enumerate(compact_groups):
            group_entries = [row for row in entries if int(row['exam']['section_id']) in group]
            
            if not group_entries:
                return reject('STUDENT_EXAMS_MISSING', f'Student group {group} has no assigned exams.')
                
            used_flags = []
            for d in range(3):
                flags = []
                first_candidates = []
                last_candidates = []
                day_start = d * slots_per_day
                day_end = day_start + slots_per_day
                
                for i, row in enumerate(group_entries):
                    flag = model.NewBoolVar(f'g{group_number}_d{d}_exam{i}')
                    model.Add(row['day'] == d).OnlyEnforceIf(flag)
                    model.Add(row['day'] != d).OnlyEnforceIf(flag.Not())
                    flags.append(flag)

                    # Absent exams cannot change the first/last active time.
                    first = model.NewIntVar(day_start, day_end, f'g{group_number}_d{d}_first_{i}')
                    last = model.NewIntVar(day_start, day_end, f'g{group_number}_d{d}_last_{i}')
                    
                    model.Add(first == row['start']).OnlyEnforceIf(flag)
                    model.Add(first == day_end).OnlyEnforceIf(flag.Not())
                    model.Add(last == row['start'] + 1).OnlyEnforceIf(flag)
                    model.Add(last == day_start).OnlyEnforceIf(flag.Not())
                    
                    first_candidates.append(first)
                    last_candidates.append(last)

                count = sum(flags)
                model.Add(count <= 3)
                
                used = model.NewBoolVar(f'g{group_number}_used_d{d}')
                model.Add(count >= 1).OnlyEnforceIf(used)
                model.Add(count == 0).OnlyEnforceIf(used.Not())
                used_flags.append(used)

                earliest = model.NewIntVar(day_start, day_end, f'g{group_number}_d{d}_earliest')
                latest = model.NewIntVar(day_start, day_end, f'g{group_number}_d{d}_latest')
                
                model.AddMinEquality(earliest, first_candidates)
                model.AddMaxEquality(latest, last_candidates)
                
                # HARD RULE: every occupied exam hour must be consecutive.
                # Independent no-overlap constraints imply that an occupied
                # span of exactly count hours contains no vacant time.
                model.Add(latest - earliest == count).OnlyEnforceIf(used)

            # Prefer fewer days without requiring all students to use Day 3.
            # Only real student groups count in this objective, not section-only
            # helper groups; otherwise shared-section exams would be overweighted.
            if group in groups:
                objectives.extend(used_flags)
                
        # Make reducing student exam days lexicographically more important than
        # moving exams earlier; a 1-day improvement cannot be outweighed by
        # the sum of every possible earlier slot change.
        day_weight = len(entries) * (3 * slots_per_day - 1) + 1
        model.Minimize(day_weight * sum(objectives) + sum(row['start'] for row in entries))
        
        solver = cp_model.CpSolver()
        solver.parameters.max_time_in_seconds = 100
        solver.parameters.num_search_workers = 8
        status = solver.Solve(model)
        
        if status not in (cp_model.OPTIMAL, cp_model.FEASIBLE):
            return reject(
                'NO_FEASIBLE_EXAMS',
                'No complete zero-vacant-time exam timetable was found. '
                'Check approved dates, existing classes, rooms, proctors, the fixed-room-per-section rule, '
                'and the same-program/year non-overlap rule. No exam was saved.',
                solver_status=solver.StatusName(status),
                zero_gap_required=True
            )
            
        result = []
        for row in entries:
            slot, room_id = solver.Value(row['start']), solver.Value(row['room'])
            d, h = divmod(slot, slots_per_day)
            hour = hour_start + h
            e, sec = row['exam'], row['section']
            
            result.append({
                'section_subject_id': int(e['section_subject_id']),
                'section_id': int(e['section_id']), 
                'section_code': sec['section_code'],
                'section_type': sec['section_type'], 
                'year_level': int(sec['year_level']),
                'subject_id': int(e['subject_id']), 
                'subject_code': e['subject_code'],
                'subject_title': e['subject_title'], 
                'proctor_id': int(e['proctor_id']),
                'proctor_name': teachers[int(e['proctor_id'])]['teacher_name'],
                'room_id': room_id, 
                'room_name': rooms[room_id]['room_name'],
                'exam_day': d + 1, 
                'exam_date': days[d].isoformat(),
                'start_time': f'{hour:02d}:00', 
                'end_time': f'{hour+1:02d}:00'
            })
            
        result.sort(key=lambda x: (x['year_level'], x['section_code'], x['exam_day'], x['start_time']))
        
        # Second-pass audit from assigned values (not CP-SAT booleans).
        problems = []
        for kind, resource in (
            ('program_year', lambda x: (x['year_level'],)),
            ('teacher',      lambda x: (x['proctor_id'],)),
            ('room',         lambda x: (x['room_id'],)),
            ('subject',      lambda x: (x['subject_id'],)),
            ('section',      lambda x: (x['section_id'],))
        ):
            seen = set()
            for x in result:
                key = (resource(x), x['exam_date'], x['start_time'])
                if key in seen:
                    problems.append(f'{kind} overlap on {x["exam_date"]} {x["start_time"]}')
                seen.add(key)
                
        day_counts = defaultdict(int)
        for st in students:
            membership = {int(st['home_section_id'])}
            if st.get('major_section_id') is not None:
                membership.add(int(st['major_section_id']))
                
            by_day = defaultdict(int)
            for x in result:
                if x['section_id'] in membership:
                    by_day[x['exam_date']] += 1
                    
            if any(n > 3 for n in by_day.values()):
                problems.append('Student daily exam limit exceeded.')
                break
                
        # Fourth-year policy is also independently checked against actual
        # output below; the solver flags alone cannot authorize the preview.
        # Independent output audit: checks section-level and ACTUAL per-student
        # home + major membership combinations without consulting CP-SAT flags.
        gap_audit = audit_zero_exam_gaps(result, sections, students, [d.isoformat() for d in days])
        
        if not gap_audit['passed']:
            problems.extend(gap_audit['issues'])
            
        if problems:
            return reject(
                'EXAM_AUDIT_FAILED',
                'Generated exam schedule failed independent result checks.',
                issues=problems[:30], 
                gap_audit=gap_audit
            )
            
        return {
            'success': True, 
            'status': 'EXAM_PREVIEW_READY', 
            'database_write': False,
            'solver_status': solver.StatusName(status), 
            'solve_seconds': round(solver.WallTime(), 2),
            'required_exams': len(exams), 
            'returned_exams': len(result), 
            'issues': [],
            'gap_audit': gap_audit, 
            'zero_gap_required': True,
            'fourth_year_exam_policy': {
                'cluster_days': [1, 2], 
                'major_day': 3,
                'cluster_exams_same_day_consecutive': True,
                'cluster_sections_checked': cluster_count,
                'major_sections_checked': major_count,
                'actual_student_memberships_checked': True,
            },
            'exam_room_policy': {
                'fixed_room_per_section_entire_exam_period': True,
                'linked_fourth_year_cluster_major_share_room': True,
                'sections_checked': fixed_room_sections_checked,
                'student_groups_checked': fixed_room_student_groups_checked,
            },
            'proctor_policy': 'DEMO: existing subject teacher serves as provisional proctor; official proctor roster not yet integrated.',
            'class_policy': 'Existing weekly class meetings are treated as occupied on matching exam weekdays.',
            'exam_dates': [d.isoformat() for d in days],
            'linked_student_section_pairs': [
                {'home_section_id': a, 'major_section_id': b}
                for a, b in sorted(linked_pairs)
            ],
            'assignments': result
        }
        
    except (ValueError, KeyError, TypeError) as exc:
        return reject('INVALID_EXAM_INPUT', str(exc))