"""Independent read-only checks for Module 4's proposed examination timetable.

No OR-Tools dependency: this module inspects actual solver output, not model flags.
All times are local wall-clock HH:MM and every exam is precisely 60 minutes.
For shared students, membership is taken from actual student rows, not section numbering.
"""
from collections import defaultdict
from datetime import date


def _clock(value):
    parts = str(value).split(':')
    if len(parts) < 2:
        raise ValueError(f'Invalid exam time: {value!r}')
        
    h, m = int(parts[0]), int(parts[1])
    
    if not (0 <= h <= 24 and 0 <= m < 60 and (h != 24 or m == 0)):
        raise ValueError(f'Invalid exam time: {value!r}')
        
    return 60 * h + m


def audit_zero_exam_gaps(assignments, sections, students, exam_dates):
    """Return evidence-based, independently computed section/student gap checks.

    'No vacant' means consecutive exam intervals within each student's actual
    combination of sections on every date when that student has >=2 exams.
    Section-level checks are also enforced for separately printed exam sheets.
    """
    issues = []
    by_section = defaultdict(list)
    section_ids = {int(x['section_id']) for x in sections.values()}
    expected_dates = {date.fromisoformat(value).isoformat() for value in exam_dates}
    
    for x in assignments:
        try:
            sid = int(x['section_id'])
            actual_date = date.fromisoformat(str(x['exam_date'])).isoformat()
            start, end = _clock(x['start_time']), _clock(x['end_time'])
            
            if sid not in section_ids:
                issues.append(f'Unknown section {sid} in examination output.')
                
            if actual_date not in expected_dates:
                issues.append(f'Exam assigned outside selected dates: {actual_date}.')
                
            if end - start != 60:
                issues.append(f'Exam in section {sid} has duration {end-start}, not 60 minutes.')
                
            room_id = int(x['room_id'])
            by_section[sid].append((
                actual_date, 
                start, 
                end, 
                str(x.get('subject_code', 'UNKNOWN')), 
                room_id
            ))
            
        except (ValueError, KeyError, TypeError) as exc:
            issues.append(f'Invalid examination assignment: {exc}')

    def check(label, events):
        dates = defaultdict(list)
        for item in events:
            dates[item[0]].append(item)
            
        for day, slots in dates.items():
            slots.sort(key=lambda item: (item[1], item[2]))
            
            for earlier, later in zip(slots, slots[1:]):
                if later[1] < earlier[2]:
                    issues.append(f'{label}: exams overlap on {day} ({earlier[3]} / {later[3]}).')
                elif later[1] > earlier[2]:
                    issues.append(
                        f'{label}: vacant {later[1]-earlier[2]} minutes on {day} '
                        f'between {earlier[3]} and {later[3]}.'
                    )
                    
            if len(slots) > 3:
                issues.append(f'{label}: {len(slots)} exams on {day}; limit is three.')

    fixed_room_sections_checked = 0
    for sid in sorted(section_ids):
        check(f'Section {sections[sid]["section_code"]}', by_section[sid])
        room_ids = {row[4] for row in by_section[sid]}
        
        if len(room_ids) != 1:
            issues.append(
                f'Section {sections[sid]["section_code"]}: all exams across Day 1-Day 3 must use one fixed room.'
            )
        fixed_room_sections_checked += 1

    # Independent BSIT fourth-year policy: verify final assigned exam dates
    # and hours without reading any CP-SAT variables, and without pairing
    # Cluster/Major sections by section number.
    dates_in_order = [date.fromisoformat(value).isoformat() for value in exam_dates]
    cluster_sections_checked = 0
    major_sections_checked = 0
    
    for sid in sorted(section_ids):
        sec = sections[sid]
        if int(sec['year_level']) != 4:
            continue
            
        section_type = str(sec['section_type']).upper()
        rows = by_section[sid]
        label = f"Fourth-year {section_type} {sec['section_code']}"
        
        if section_type == 'CLUSTER':
            cluster_sections_checked += 1
            if len(rows) != 3 or len({x[3] for x in rows}) != 3:
                issues.append(f'{label}: exactly three distinct Cluster exams are required.')
                
            actual_days = {x[0] for x in rows}
            if len(actual_days) != 1 or not actual_days.issubset(set(dates_in_order[:2])):
                issues.append(f'{label}: all three Cluster exams must be on the same Day 1 or Day 2.')
            # The section-level `check()` above independently rejects any
            # overlap or vacant time between Cluster exams.
            
        elif section_type == 'MAJOR':
            major_sections_checked += 1
            if not rows:
                issues.append(f'{label}: a Major exam is missing.')
            elif any(x[0] != dates_in_order[2] for x in rows):
                issues.append(f'{label}: every Major exam must be on Day 3.')

    unique_groups = set()
    roster_count = defaultdict(set)
    
    for student in students:
        try:
            home = int(student['home_section_id'])
            ids = {home}
            
            if student.get('major_section_id') is not None:
                ids.add(int(student['major_section_id']))
                
            if not ids.issubset(section_ids):
                issues.append('Student references an unknown home or major section.')
                continue
                
            group = tuple(sorted(ids))
            unique_groups.add(group)
            
            for sid in group:
                roster_count[sid].add(str(student['student_id']))
                
        except (ValueError, KeyError, TypeError) as exc:
            issues.append(f'Invalid student section membership: {exc}')

    if not students:
        issues.append('Student section memberships were not loaded.')
        
    for sid in sorted(section_ids):
        expected = int(sections[sid]['student_count'])
        if len(roster_count[sid]) != expected:
            issues.append(
                f'Section {sections[sid]["section_code"]} has {len(roster_count[sid])} '
                f'student memberships, expected {expected}.'
            )

    fixed_room_student_groups_checked = 0
    for group in sorted(unique_groups):
        combined = [row for sid in group for row in by_section[sid]]
        label = 'Student section group ' + '/'.join(sections[sid]['section_code'] for sid in group)
        check(label, combined)
        
        room_ids = {row[4] for row in combined}
        if len(room_ids) != 1:
            issues.append(f'{label}: Cluster/Major and regular exams must keep one fixed room for the full exam period.')
        fixed_room_student_groups_checked += 1

    return {
        'passed': not issues,
        'status': 'ZERO_GAP_AUDIT_PASSED' if not issues else 'ZERO_GAP_AUDIT_FAILED',
        'total_issues': len(issues),
        'issues': issues[:30],
        'checked_students': len(students),
        'checked_student_groups': len(unique_groups),
        'checked_sections': len(section_ids),
        'fourth_year_cluster_sections_checked': cluster_sections_checked,
        'fourth_year_major_sections_checked': major_sections_checked,
        'fixed_room_sections_checked': fixed_room_sections_checked,
        'fixed_room_student_groups_checked': fixed_room_student_groups_checked,
        'fixed_room_policy': 'ONE_ROOM_PER_SECTION_OR_LINKED_STUDENT_GROUP_FOR_DAY_1_TO_DAY_3',
        'vacant_minutes': 0 if not issues else None,
    }