"""BCP Module 4: DEMO exam timetable preview, no database writes.
Inputs come from the PHP database loader, NEVER from the browser directly.
BCP exam-day policy suspends recurring regular classes on the exact selected exam dates.
The weekly class timetable remains saved and resumes on non-exam dates.
Each section receives one fixed same-program proctor for the whole exam period;
that proctor may handle another section later when exam-duty times do not overlap.
"""
from collections import defaultdict
from datetime import date
import re
from ortools.sat.python import cp_model
from app.exam_gap_audit import audit_zero_exam_gaps

DAYS = ('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')
# Four fixed three-hour shifts; EACH SUBJECT takes one hour inside its shift.
EXAM_SHIFTS = ((6, 9), (9, 12), (12, 15), (15, 18))
EXAM_HOURS = tuple(hour for start, end in EXAM_SHIFTS for hour in range(start, end))


def minutes(clock):
    parts = str(clock).split(':')
    if len(parts) < 2:
        raise ValueError('Invalid time: ' + str(clock))
    return int(parts[0]) * 60 + int(parts[1])


def covers(windows, day, start, end):
    return any(w['day_of_week'] == day and w['availability_status'] == 'AVAILABLE'
               and minutes(w['start_time']) <= start and end <= minutes(w['end_time'])
               for w in windows) and not any(
        w['day_of_week'] == day and w['availability_status'] == 'UNAVAILABLE'
        and start < minutes(w['end_time']) and minutes(w['start_time']) < end
        for w in windows)


def blocked(existing, key, resource_id, day, start, end):
    return any(int(m[key]) == resource_id and m['day_of_week'] == day
               and start < minutes(m['end_time']) and minutes(m['start_time']) < end
               for m in existing if m.get(key) is not None)


def reject(code, message, **details):
    return {'success': False, 'status': code, 'message': message,
            'database_write': False, **details}


def allowed_day_patterns(exam_count):
    """Compact BCP exam-day distributions, max three exams per day."""
    if exam_count < 1 or exam_count > 9:
        return []
    if exam_count <= 3:
        return [(exam_count, 0, 0), (0, exam_count, 0), (0, 0, exam_count)]
    if exam_count == 4:
        return [(2, 2, 0), (0, 2, 2)]
    if exam_count == 5:
        return [(3, 2, 0), (2, 3, 0), (0, 3, 2), (0, 2, 3)]
    if exam_count == 6:
        return [(3, 3, 0), (0, 3, 3)]
    if exam_count == 7:
        return [(3, 2, 2), (2, 3, 2), (2, 2, 3)]
    if exam_count == 8:
        return [(3, 3, 2), (3, 2, 3), (2, 3, 3)]
    return [(3, 3, 3)]


def solve_exam(data):
    try:
        program_code = str(data.get('program_code') or '').strip().upper()
        if not program_code or data.get('data_origin') != 'DEMO':
            return reject('PROGRAM_NOT_CONFIGURED', 'A valid DEMO program is required for examination generation.')
        days = [date.fromisoformat(x) for x in data['exam_dates']]
        if len(days) != 3 or len(set(days)) != 3 or days != sorted(days):
            return reject('INVALID_DATES', 'Choose three distinct examination dates in ascending order.')
        if any(x.weekday() >= 6 for x in days):
            return reject('INVALID_DATES', 'Sunday exams are not enabled in this DEMO.')
        hour_start, hour_end = int(data['start_hour']), int(data['end_hour'])
        if (hour_start, hour_end) != (6, 18):
            return reject('INVALID_EXAM_WINDOW', 'Exam shifts are fixed from 06:00 to 18:00.')
        slots_per_day = len(EXAM_HOURS)
        sections = {int(r['section_id']): r for r in data['sections']}
        rooms = {int(r['room_id']): r for r in data['rooms']}
        teachers = {int(r['teacher_id']): r for r in data['teachers']}
        exams = data['exams']
        existing = data['existing_classes']
        existing_exams = data.get('existing_exams', [])
        suspend_regular_classes = bool(data.get('regular_classes_suspended_on_exam_dates', False))

        # Index already-saved exams once before candidate generation.
        #
        # Previous implementation scanned the ENTIRE existing_exams list for
        # every candidate exam/day/hour/proctor/room combination. That becomes
        # extremely expensive as soon as another program already has an ACTIVE
        # exam timetable. Keep the exact same cross-program conflict rules, but
        # reduce each lookup to only the relevant resource/date (or subject/date)
        # intervals.
        saved_resource_intervals = {
            'proctor_id': defaultdict(list),
            'room_id': defaultdict(list),
        }
        saved_subject_intervals = defaultdict(list)
        current_program_id = int(data['program_id'])

        for row in existing_exams:
            exam_date = str(row.get('exam_date') or '')
            saved_start = minutes(row['start_time'])
            saved_end = minutes(row['end_time'])

            for key in ('proctor_id', 'room_id'):
                value = row.get(key)
                if value is not None:
                    saved_resource_intervals[key][(int(value), exam_date)].append(
                        (saved_start, saved_end)
                    )

            # Paper-rotation protection applies only to the same program,
            # same year level, and same subject, exactly as before.
            if int(row.get('program_id', -1)) == current_program_id:
                saved_subject_intervals[(
                    int(row.get('year_level', -1)),
                    int(row.get('subject_id', -1)),
                    exam_date,
                )].append((saved_start, saved_end))

        def saved_exam_blocked(key, resource_id, actual_date, start, end):
            intervals = saved_resource_intervals[key].get(
                (resource_id, actual_date.isoformat()), ()
            )
            return any(
                start < saved_end and saved_start < end
                for saved_start, saved_end in intervals
            )

        def saved_same_subject_blocked(year_level, subject_id, actual_date, start, end):
            intervals = saved_subject_intervals.get(
                (year_level, subject_id, actual_date.isoformat()), ()
            )
            return any(
                start < saved_end and saved_start < end
                for saved_start, saved_end in intervals
            )
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
        # Validate section references. Proctors are NOT tied to subject teachers.
        # Module 4 assigns one fixed same-program proctor per section for the
        # entire selected exam period. A proctor may serve another section later
        # when the actual duty intervals do not overlap.
        eligible_proctor_ids = sorted(
            teacher_id for teacher_id, teacher in teachers.items()
            if int(teacher['program_id']) == int(data['program_id'])
        )
        if not eligible_proctor_ids:
            return reject('NO_PROGRAM_PROCTORS',
                          'No active same-program proctors are available for this examination timetable.')
        for e in exams:
            sec = sections.get(int(e['section_id']))
            if not sec or int(sec['program_id']) != int(data['program_id']):
                return reject('BAD_SECTION', 'An exam references a missing or foreign section.')

        model = cp_model.CpModel()
        # Paper-rotation rule: only the SAME subject within the SAME year level
        # is prevented from running simultaneously. Different subjects and
        # different year levels may run in parallel when other resources allow.
        by_subject_year, by_section, by_room, by_teacher = (defaultdict(list) for _ in range(4))
        section_proctor = {}
        entries = []

        for idx, e in enumerate(exams):
            sid = int(e['section_id'])
            sec = sections[sid]
            count = int(sec['student_count'])
            if sid not in section_proctor:
                section_proctor[sid] = model.NewIntVarFromDomain(
                    cp_model.Domain.FromValues(eligible_proctor_ids),
                    f'proctor_section_{sid}'
                )
            proctor_var = section_proctor[sid]
            eligible = []

            for day_idx, actual_date in enumerate(days):
                weekday = actual_date.strftime('%A')
                for hour_idx, hour in enumerate(EXAM_HOURS):
                    start_min, end_min = 60 * hour, 60 * (hour + 1)
                    if any((minute, minute + 30) not in db_slots[weekday]
                           for minute in range(start_min, end_min, 30)):
                        continue

                    if saved_same_subject_blocked(int(sec['year_level']), int(e['subject_id']), actual_date, start_min, end_min):
                        continue

                    for teacher_id in eligible_proctor_ids:
                        if not covers(teacher_windows[teacher_id], weekday, start_min, end_min):
                            continue
                        if (not suspend_regular_classes and
                                blocked(existing, 'teacher_id', teacher_id, weekday, start_min, end_min)):
                            continue
                        if saved_exam_blocked('proctor_id', teacher_id, actual_date, start_min, end_min):
                            continue

                        for room_id, room in rooms.items():
                            if int(room['capacity']) < count:
                                continue
                            # Department/program protection: Module 4 only receives
                            # active same-program teachers and program/shared rooms. Foreign
                            # program-owned resources are never eligible.
                            if room.get('program_id') is not None and int(room['program_id']) != int(data['program_id']):
                                continue
                            if not covers(room_windows[room_id], weekday, start_min, end_min):
                                continue
                            if (not suspend_regular_classes and
                                    blocked(existing, 'room_id', room_id, weekday, start_min, end_min)):
                                continue
                            if saved_exam_blocked('room_id', room_id, actual_date, start_min, end_min):
                                continue
                            slot = day_idx * slots_per_day + hour_idx
                            eligible.append((slot, room_id, teacher_id))

            if not eligible:
                return reject(
                    'NO_ELIGIBLE_EXAM_SLOT',
                    f"No available one-hour exam slot within the four official shifts, room, and same-program section proctor "
                    f"for {e['subject_code']} / section {sec['section_code']}. "
                    'Choose other dates or review room and proctor availability for the selected hour.'
                )

            allowed_starts = sorted(set(slot for slot, _, _ in eligible))
            start = model.NewIntVarFromDomain(cp_model.Domain.FromValues(allowed_starts), f'start_{idx}')
            end = model.NewIntVar(1, 3 * slots_per_day, f'end_{idx}')
            model.Add(end == start + 1)

            allowed_rooms = sorted(set(room for _, room, _ in eligible))
            room_var = model.NewIntVarFromDomain(cp_model.Domain.FromValues(allowed_rooms), f'room_{idx}')
            model.AddAllowedAssignments([start, room_var, proctor_var], eligible)

            day_var = model.NewIntVar(0, 2, f'day_{idx}')
            model.AddDivisionEquality(day_var, start, slots_per_day)
            subject_year_key = (int(sec['year_level']), int(e['subject_id']))
            by_subject_year[subject_year_key].append(start)
            by_section[sid].append(start)

            for room_id in allowed_rooms:
                selected = model.NewBoolVar(f'room_{idx}_{room_id}')
                model.Add(room_var == room_id).OnlyEnforceIf(selected)
                model.Add(room_var != room_id).OnlyEnforceIf(selected.Not())
                by_room[room_id].append(
                    model.NewOptionalIntervalVar(start, 1, end, selected,
                                                 f'room_interval_{idx}_{room_id}')
                )

            for teacher_id in sorted(set(teacher for _, _, teacher in eligible)):
                selected = model.NewBoolVar(f'proctor_{idx}_{teacher_id}')
                model.Add(proctor_var == teacher_id).OnlyEnforceIf(selected)
                model.Add(proctor_var != teacher_id).OnlyEnforceIf(selected.Not())
                by_teacher[teacher_id].append(
                    model.NewOptionalIntervalVar(start, 1, end, selected,
                                                 f'proctor_interval_{idx}_{teacher_id}')
                )

            entries.append({
                'exam': e, 'section': sec, 'start': start, 'room': room_var,
                'proctor': proctor_var, 'day': day_var, 'eligible': set(eligible)
            })

        # ==============================================================
        # HARD RULE: ONE ROOM PER SECTION FOR THE ENTIRE EXAM PERIOD
        # Day 1, Day 2, and Day 3 exams of the same section must use the
        # exact same physical room. The solver may choose the room, but once
        # chosen it becomes the section's locked exam room.
        # ==============================================================
        # HARD RULE: each official section keeps its selected three-hour
        # exam shift across every exam date.  Only the subjects may change.
        # Starts are encoded as (day * 12 + local hour), where each shift
        # contains exactly three consecutive one-hour starts.
        section_shift_anchor = {}
        allowed_shift_hours = [(slot, shift)
                               for slot in range(3 * slots_per_day)
                               for shift in range(4)
                               if ((slot % slots_per_day) // 3) == shift]
        for row in entries:
            sid = int(row['exam']['section_id'])
            if sid not in section_shift_anchor:
                section_shift_anchor[sid] = model.NewIntVar(0, 3, f'section_{sid}_fixed_shift')
            model.AddAllowedAssignments(
                [row['start'], section_shift_anchor[sid]], allowed_shift_hours)

        # A shift is a capacity bucket, not a requirement to fabricate sections.
        # This generator is program-scoped: other programs' sections are not
        # silently included in the 30-section target.
        shift_memberships = defaultdict(list)
        for sid, shift in section_shift_anchor.items():
            for shift_id in range(4):
                member = model.NewBoolVar(f'section_{sid}_in_shift_{shift_id}')
                model.Add(shift == shift_id).OnlyEnforceIf(member)
                model.Add(shift != shift_id).OnlyEnforceIf(member.Not())
                shift_memberships[shift_id].append(member)
        for members in shift_memberships.values():
            model.Add(sum(members) <= 30)

        # HARD DEPLOYMENT ORDER: no higher-year section may be assigned an
        # earlier shift than a lower-year section. Thus fourth year cannot
        # appear in shift 1 while first year is still assigned shift 2.
        # Sections of different years CAN share a shift if resources permit.
        deployment_years = defaultdict(list)
        for sid, shift_var in section_shift_anchor.items():
            deployment_years[int(sections[sid]['year_level'])].append(shift_var)
        sorted_deployment_years = sorted(deployment_years)
        for earlier_year, later_year in zip(sorted_deployment_years, sorted_deployment_years[1:]):
            # Group min/max reduces the number of cross-year constraints.
            earlier_max = model.NewIntVar(0, 3, f'year_{earlier_year}_last_shift')
            later_min = model.NewIntVar(0, 3, f'year_{later_year}_first_shift')
            model.AddMaxEquality(earlier_max, deployment_years[earlier_year])
            model.AddMinEquality(later_min, deployment_years[later_year])
            model.Add(earlier_max <= later_min)

        # SAME-YEAR SUBJECT ROTATION — only regular official sections.
        # Year 1 (9 exams): 3+3+3, Year 2 (8): 3+3+2,
        # Year 3 (6): 3+3+0. Fourth-year policy remains untouched.
        # Group trios by matching subject IDs, not by section number alone.
        year_sections = defaultdict(set)
        section_subject_rows = defaultdict(dict)
        for row in entries:
            sid = int(row['exam']['section_id'])
            year_sections[int(row['section']['year_level'])].add(sid)
            section_subject_rows[sid][int(row['exam']['subject_id'])] = row
        for year, ids in year_sections.items():
            if year not in (1, 2, 3):
                continue
            expected = {1: 9, 2: 8, 3: 6}[year]
            same_roster = defaultdict(list)
            for sid in ids:
                subjects = tuple(sorted(section_subject_rows[sid]))
                if len(subjects) == expected:
                    same_roster[subjects].append(sid)
            for subjects, candidates in same_roster.items():
                candidates.sort(key=lambda sid: (str(sections[sid]['section_code']), sid))
                for offset in range(0, len(candidates) - 2, 3):
                    trio = candidates[offset:offset + 3]
                    if len(trio) != 3:
                        continue
                    # A Year-2 trio must share the same Day-3 subject pair.
                    # Prefer SOSLIT + PE3 when both are on the actual official
                    # roster; otherwise choose one deterministic common pair.
                    # Do not hard-code these subjects into other programs.
                    if year == 2:
                        subject_codes = {
                            subject_id: str(section_subject_rows[trio[0]][subject_id]['exam'].get('subject_code') or '').upper()
                            for subject_id in subjects
                        }
                        preferred = [subject_id for code in ('SOSLIT', 'PE3')
                                     for subject_id in subjects if subject_codes[subject_id] == code]
                        day3_pair = tuple(preferred) if len(preferred) == 2 else tuple(subjects[-2:])
                        # Three sections and two papers cannot all occupy one
                        # 3-hour shift under the existing strict same-subject
                        # no-overlap rule: the third section must use another
                        # shift, otherwise the A/B/A rotation repeats paper A
                        # simultaneously. Preserve paper security rather than
                        # disabling the hard conflict constraint.
                        model.Add(section_shift_anchor[trio[2]] != section_shift_anchor[trio[0]])
                        scheduled_day12 = tuple(s for s in subjects if s not in day3_pair)
                    else:
                        scheduled_day12 = subjects
                        for sid in trio[1:]:
                            model.Add(section_shift_anchor[sid] == section_shift_anchor[trio[0]])
                    for day in range(3 if year == 1 else 2):
                        selected = scheduled_day12[day * 3:day * 3 + 3]
                        for section_index, sid in enumerate(trio):
                            for hour_index in range(3):
                                subject_id = selected[(hour_index + section_index) % 3]
                                row = section_subject_rows[sid][subject_id]
                                model.Add(row['start'] == day * slots_per_day
                                          + section_shift_anchor[sid] * 3 + hour_index)
                    if year == 2:
                        # 2nd year Day 3: A/B, B/A, A/B, using the SAME pair.
                        # All two exams are consecutive, beginning with the
                        # first hour of the section's own fixed shift.
                        for section_index, sid in enumerate(trio):
                            for hour_index in range(2):
                                subject_id = day3_pair[(hour_index + section_index) % 2]
                                row = section_subject_rows[sid][subject_id]
                                model.Add(row['start'] == 2 * slots_per_day
                                          + section_shift_anchor[sid] * 3 + hour_index)

        section_room_anchor = {}
        for row in entries:
            sid = int(row['exam']['section_id'])
            if sid not in section_room_anchor:
                section_room_anchor[sid] = row['room']
            else:
                model.Add(row['room'] == section_room_anchor[sid])

        # ROOM CLUSTERING (SOFT): keep sections in the same year level in
        # neighboring numbered classrooms for fast examination-paper rotation.
        # The assignment is still decided by OR-Tools, never the staff display.
        # Model the actual numeric room names (BSIT 201, 202, ... 301) rather
        # than arbitrary database IDs, which do not express room proximity.
        # A floor change costs roughly 100 room-number units.
        room_position = {}
        for room_id, room in rooms.items():
            name = str(room.get('room_name') or '')
            match = re.search(r'(\d{3,4})\s*$', name)
            room_position[room_id] = int(match.group(1)) if match else 10000 + int(room_id)

        section_position = {}
        position_choices = sorted(set(room_position.values()))
        for sid, room_var in section_room_anchor.items():
            position = model.NewIntVarFromDomain(
                cp_model.Domain.FromValues(position_choices), f'section_{sid}_room_position')
            model.AddAllowedAssignments(
                [room_var, position], [(rid, pos) for rid, pos in room_position.items()])
            section_position[sid] = position

        # Minimize the physical span occupied by each year level.  This is a
        # preference, NOT a hard restriction: capacity, real availability,
        # conflicts, student memberships and same-subject paper-rotation win.
        year_positions = defaultdict(list)
        for sid, position in section_position.items():
            year_positions[int(sections[sid]['year_level'])].append(position)
        clustering_spans = []
        low_pos, high_pos = min(position_choices), max(position_choices)
        for year, positions in year_positions.items():
            if len(positions) < 2:
                continue
            lowest = model.NewIntVar(low_pos, high_pos, f'year_{year}_min_room')
            highest = model.NewIntVar(low_pos, high_pos, f'year_{year}_max_room')
            span = model.NewIntVar(0, high_pos - low_pos, f'year_{year}_room_span')
            model.AddMinEquality(lowest, positions)
            model.AddMaxEquality(highest, positions)
            model.Add(span == highest - lowest)
            clustering_spans.append(span)

        # BCP paper-rotation policy:
        # - the same subject within the same year level must alternate between sections;
        # - different subjects may run at the same time;
        # - the same subject in another year level is not globally blocked;
        # - a section itself can never sit two exams simultaneously.
        for group in (by_subject_year, by_section):
            for slots in group.values():
                if len(slots) > 1:
                    model.AddAllDifferent(slots)

        # Rooms and proctors may be reused later on the same day, but never at
        # overlapping exam-duty times. This is what allows one teacher to finish
        # one section and then proctor another section/year afterward.
        for intervals in by_room.values():
            if len(intervals) > 1:
                model.AddNoOverlap(intervals)
        for intervals in by_teacher.values():
            if len(intervals) > 1:
                model.AddNoOverlap(intervals)

        # ==============================================================
        # BSIT 4TH YEAR SPECIAL POLICY: CLUSTER ON DAY 2; MAJOR ON DAY 3.
        # These restrictions apply to OFFICIAL section types, not section
        # numbers or assumed one-to-one Cluster/Major student pairings.
        # Section-level and actual-student zero-gap rules remain below.
        # ==============================================================
        fourth_year_exams = defaultdict(list)
        if program_code == 'BSIT':
            for row in entries:
                sec = row['section']
                if int(sec['year_level']) == 4:
                    fourth_year_exams[int(row['exam']['section_id'])].append(row)

        cluster_count = 0
        major_count = 0
        for sid, sec in sections.items():
            if program_code != 'BSIT' or int(sec['year_level']) != 4:
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
                        section_id=sid, received_exams=len(rows),
                    )
                # BCP no-skipped-day rule: because the linked Major exam is on
                # Day 3, all three Cluster exams are fixed to Day 2. This avoids
                # the tiring Day 1 + Day 3 pattern with an empty Day 2.
                for row in rows:
                    model.Add(row['day'] == 1)  # Day 2, zero-based index.
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
                return reject('INCOMPLETE_STUDENT_MEMBERSHIP',
                    f"Section {sec['section_code']} has {len(memberships[sid])} mapped students but expects {sec['student_count']}.")
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
        groups = {tuple(sorted({int(st['home_section_id'])} | (
            {int(st['major_section_id'])} if st.get('major_section_id') is not None else set())))
            for st in students}
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
            if len(group_entries) > 9:
                return reject('TOO_MANY_EXAMS_PER_STUDENT_GROUP',
                              f'Student group {group} has {len(group_entries)} exams; three days at three exams per day supports at most nine.')

            used_flags = []
            count_vars = []
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
                    first = model.NewIntVar(day_start, day_end,
                                            f'g{group_number}_d{d}_first_{i}')
                    last = model.NewIntVar(day_start, day_end,
                                           f'g{group_number}_d{d}_last_{i}')
                    model.Add(first == row['start']).OnlyEnforceIf(flag)
                    model.Add(first == day_end).OnlyEnforceIf(flag.Not())
                    model.Add(last == row['start'] + 1).OnlyEnforceIf(flag)
                    model.Add(last == day_start).OnlyEnforceIf(flag.Not())
                    first_candidates.append(first)
                    last_candidates.append(last)

                count_var = model.NewIntVar(0, 3, f'g{group_number}_count_d{d}')
                model.Add(count_var == sum(flags))
                count_vars.append(count_var)
                used = model.NewBoolVar(f'g{group_number}_used_d{d}')
                model.Add(count_var >= 1).OnlyEnforceIf(used)
                model.Add(count_var == 0).OnlyEnforceIf(used.Not())
                used_flags.append(used)

                earliest = model.NewIntVar(day_start, day_end,
                                            f'g{group_number}_d{d}_earliest')
                latest = model.NewIntVar(day_start, day_end,
                                          f'g{group_number}_d{d}_latest')
                model.AddMinEquality(earliest, first_candidates)
                model.AddMaxEquality(latest, last_candidates)
                # HARD RULE: every occupied exam hour must be consecutive.
                model.Add(latest - earliest == count_var).OnlyEnforceIf(used)
                # All exams on this day for a section/student group must fit
                # INSIDE ONE of the official three-hour shifts.
                first_shift = model.NewIntVar(0, 3, f'g{group_number}_d{d}_first_shift')
                last_shift = model.NewIntVar(0, 3, f'g{group_number}_d{d}_last_shift')
                first_local = model.NewIntVar(0, slots_per_day - 1, f'g{group_number}_d{d}_first_local')
                last_local = model.NewIntVar(0, slots_per_day - 1, f'g{group_number}_d{d}_last_local')
                model.Add(first_local == earliest - day_start).OnlyEnforceIf(used)
                model.Add(last_local == latest - 1 - day_start).OnlyEnforceIf(used)
                model.AddDivisionEquality(first_shift, first_local, 3)
                model.AddDivisionEquality(last_shift, last_local, 3)
                model.Add(first_shift == last_shift).OnlyEnforceIf(used)

            # Actual fourth-year Cluster + Major students have the BCP-specific
            # 3+1 pattern: three Cluster exams on Day 2, one Major on Day 3.
            group_sections = [sections[sid] for sid in group]
            special_fourth = (
                program_code == 'BSIT'
                and group in groups
                and all(int(sec['year_level']) == 4 for sec in group_sections)
                and any(str(sec['section_type']).upper() == 'CLUSTER' for sec in group_sections)
                and any(str(sec['section_type']).upper() == 'MAJOR' for sec in group_sections)
            )
            if special_fourth:
                model.AddAllowedAssignments(count_vars, [(0, 3, 1)])
            else:
                # Fixed distributions for normal BSIT Year 2 and Year 3.
                # Keep the actual linked Fourth Year student policy intact.
                member_years = {int(sec['year_level']) for sec in group_sections}
                if program_code == 'BSIT' and member_years == {2} and len(group_entries) == 8:
                    patterns = [(3, 3, 2)]
                elif program_code == 'BSIT' and member_years == {3} and len(group_entries) == 6:
                    patterns = [(3, 3, 0)]
                else:
                    patterns = allowed_day_patterns(len(group_entries))
                if not patterns:
                    return reject('INVALID_EXAM_DAY_DISTRIBUTION',
                                  f'No BCP day-distribution policy is defined for {len(group_entries)} exams.')
                model.AddAllowedAssignments(count_vars, patterns)

            # Only real student groups count in the secondary objective.
            if group in groups:
                objectives.extend(used_flags)

        # Hard day distributions already minimize unnecessary attendance days.
        # Secondary preference: use earlier available hours where all hard rules allow.
        # Minimize year-level room spread first, then the existing compact-day
        # and early-hour preferences. Room proximity never overrides hard rules.
        day_weight = len(entries) * (3 * slots_per_day - 1) + 1
        proximity_weight = day_weight * (len(objectives) + 1) + len(entries) * (3 * slots_per_day)
        # First-year sections get the strongest early-shift preference, then
        # second, third and fourth year. This is a SOFT priority: never break
        # hard availability, fixed-shift, paper-rotation or room rules.
        year_order = sorted({int(sec['year_level']) for sec in sections.values()})
        priority_cost = sum(
            (len(year_order) - year_order.index(int(sections[sid]['year_level'])))
            * section_shift_anchor[sid] for sid in section_shift_anchor
        )
        # Make the early-shift preference decisive over room proximity while
        # retaining the latter to cluster same-year sections physically.
        priority_weight = (proximity_weight * (len(clustering_spans) *
                           (max(room_position.values()) - min(room_position.values()) + 1) + 1)
                           + day_weight * len(objectives) + len(entries) * 36 + 1)
        # Among feasible clustered layouts, prefer the LOWEST numbered
        # eligible classrooms (201, 202, 203...) rather than an arbitrary
        # equally compact cluster such as 303, 304, 305. Each section is
        # counted once; a section's room remains fixed on all three days.
        # Higher priority: hard year order, hard conflicts, then proximity.
        ranked_rooms = sorted(room_position, key=lambda rid: (room_position[rid], rid))
        room_rank = {rid: rank for rank, rid in enumerate(ranked_rooms)}
        ranked_section_rooms = []
        for sid, assigned_room in section_room_anchor.items():
            rank_var = model.NewIntVar(0, len(ranked_rooms) - 1,
                                       f'section_{sid}_room_rank')
            model.AddAllowedAssignments(
                [assigned_room, rank_var], list(room_rank.items()))
            ranked_section_rooms.append(rank_var)
        room_floor_cost = sum(ranked_section_rooms)
        # The lower-numbered-room preference beats day-time aesthetics,
        # but cannot beat year-room proximity or any hard constraint.
        room_floor_weight = day_weight * len(objectives) + len(entries) * 36 + 1
        proximity_weight = room_floor_weight * (len(section_room_anchor) * len(ranked_rooms) + 1)
        priority_weight = proximity_weight * (len(clustering_spans) *
                           (max(room_position.values()) - min(room_position.values()) + 1) + 1)
        model.Minimize(priority_weight * priority_cost
                       + proximity_weight * sum(clustering_spans)
                       + room_floor_weight * room_floor_cost
                       + day_weight * sum(objectives)
                       + sum(row['start'] for row in entries))
        solver = cp_model.CpSolver()
        solver.parameters.max_time_in_seconds = 100
        solver.parameters.num_search_workers = 8
        status = solver.Solve(model)
        if status not in (cp_model.OPTIMAL, cp_model.FEASIBLE):
            return reject('NO_FEASIBLE_EXAMS',
                          'No complete zero-vacant-time exam timetable was found. '
                          'Check the selected exam dates, rooms, proctor availability, '
                          'student/section conflicts, and fourth-year rules. No exam was saved.',
                          solver_status=solver.StatusName(status),
                          zero_gap_required=True)
        result = []
        for row in entries:
            slot, room_id = solver.Value(row['start']), solver.Value(row['room'])
            d, h = divmod(slot, slots_per_day)
            exam_start = EXAM_HOURS[h]
            e, sec = row['exam'], row['section']
            proctor_id = solver.Value(row['proctor'])
            result.append({'section_subject_id': int(e['section_subject_id']),
                'section_id': int(e['section_id']), 'section_code': sec['section_code'],
                'section_type': sec['section_type'], 'year_level': int(sec['year_level']),
                'subject_id': int(e['subject_id']), 'subject_code': e['subject_code'],
                'subject_title': e['subject_title'], 'proctor_id': proctor_id,
                'proctor_name': teachers[proctor_id]['teacher_name'],
                'room_id': room_id, 'room_name': rooms[room_id]['room_name'],
                'exam_day': d + 1, 'exam_date': days[d].isoformat(),
                'start_time': f'{exam_start:02d}:00', 'end_time': f'{exam_start + 1:02d}:00'})
        result.sort(key=lambda e: (e['year_level'], e['section_code'], e['exam_day'], e['start_time']))
        # Second-pass audit from assigned values (not CP-SAT booleans).
        problems = []
        for kind, resource in (('teacher', lambda x: (x['proctor_id'],)),
                               ('room', lambda x: (x['room_id'],)),
                               ('subject-within-year', lambda x: (x['year_level'], x['subject_id'])),
                               ('section', lambda x: (x['section_id'],))):
            seen = set()
            for x in result:
                key = (resource(x), x['exam_date'], x['start_time'])
                if key in seen:
                    problems.append(f'{kind} overlap on {x["exam_date"]} {x["start_time"]}')
                seen.add(key)
        section_proctor_audit = {}
        for x in result:
            sid = int(x['section_id'])
            pid = int(x['proctor_id'])
            if sid in section_proctor_audit and section_proctor_audit[sid] != pid:
                problems.append(f'Section {x["section_code"]} changed proctor during the exam period.')
            section_proctor_audit[sid] = pid
            if pid not in teachers or int(teachers[pid]['program_id']) != int(data['program_id']):
                problems.append(f'Section {x["section_code"]} has a foreign-program proctor.')

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
        gap_audit = audit_zero_exam_gaps(result, sections, students,
                                        [d.isoformat() for d in days], program_code=program_code)
        if not gap_audit['passed']:
            problems.extend(gap_audit['issues'])
        if problems:
            return reject('EXAM_AUDIT_FAILED',
                          'Generated exam schedule failed independent result checks.',
                          issues=problems[:30], gap_audit=gap_audit)
        return {'success': True, 'status': 'EXAM_PREVIEW_READY', 'database_write': False,
                'solver_status': solver.StatusName(status), 'solve_seconds': round(solver.WallTime(), 2),
                'required_exams': len(exams), 'returned_exams': len(result), 'issues': [],
                'gap_audit': gap_audit, 'zero_gap_required': True,
                'fourth_year_exam_policy': {
                    'enabled': program_code == 'BSIT',
                    'cluster_days': [2] if program_code == 'BSIT' else [],
                    'major_day': 3 if program_code == 'BSIT' else None,
                    'cluster_exams_same_day_consecutive': program_code == 'BSIT',
                    'cluster_sections_checked': cluster_count,
                    'major_sections_checked': major_count,
                    'actual_student_memberships_checked': True,
                },
                'proctor_policy': (f'One fixed active {program_code} proctor is assigned per section for the entire exam period. '
                                   'The same proctor may serve another section/year later at non-overlapping times; '
                                   'foreign-program/department proctors are not eligible.'),
                'paper_rotation_policy': ('Different subjects may run in parallel. The same subject within the same '
                                          'year level must use different exam times so test papers can rotate between sections.'),
                'day_distribution_policy': ('Compact BCP distribution is enforced: 6 exams use 3+3 on consecutive days; '
                                            'BSIT Year 2 uses 3+3+2 and BSIT Year 3 uses 3+3+0; other years use compact distributions. '
                                            'Day 1 + Day 3 with an empty Day 2 is not allowed.'),
                'class_policy': ('Selected examination dates suspend recurring regular classes; '
                                 'saved weekly classes remain stored and resume on non-exam dates.'),
                'exam_dates': [d.isoformat() for d in days],
                'linked_student_section_pairs': [{'home_section_id': a, 'major_section_id': b}
                                                 for a, b in sorted(linked_pairs)],
                'assignments': result}
    except (ValueError, KeyError, TypeError) as exc:
        return reject('INVALID_EXAM_INPUT', str(exc))
