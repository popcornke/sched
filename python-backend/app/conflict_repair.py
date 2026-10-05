from __future__ import annotations

from collections import defaultdict
from typing import Any

from fastapi import APIRouter, Body, HTTPException
from ortools.sat.python import cp_model

router = APIRouter(prefix="/api/conflicts", tags=["conflicts"])

DAYS = ("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday")
MWF = {"Monday", "Wednesday", "Friday"}
TTHS = {"Tuesday", "Thursday", "Saturday"}
SLOT_MINUTES = 30
SCHOOL_START = 6 * 60
SCHOOL_END = 21 * 60
MAX_REPAIR_SECONDS = 30.0


def _minutes(value: str) -> int:
    parts = str(value).split(":")
    if len(parts) < 2:
        raise ValueError(f"Invalid time: {value}")
    return int(parts[0]) * 60 + int(parts[1])


def _clock(total: int) -> str:
    return f"{total // 60:02d}:{total % 60:02d}"


def _overlap(a_start: int, a_end: int, b_start: int, b_end: int) -> bool:
    return a_start < b_end and b_start < a_end


def _window_allows(rows: list[dict[str, Any]], entity_id: int, day: str, start: int, end: int, id_key: str) -> bool:
    relevant = [
        row for row in rows
        if int(row.get(id_key, 0)) == entity_id and str(row.get("day_of_week", "")) == day
    ]
    if not relevant:
        return False

    for row in relevant:
        status = str(row.get("status", row.get("availability_status", "AVAILABLE"))).upper()
        rs = _minutes(str(row.get("start_time", "00:00")))
        re = _minutes(str(row.get("end_time", "00:00")))
        if status == "UNAVAILABLE" and _overlap(start, end, rs, re):
            return False

    return any(
        str(row.get("status", row.get("availability_status", "AVAILABLE"))).upper() == "AVAILABLE"
        and _minutes(str(row.get("start_time", "00:00"))) <= start
        and _minutes(str(row.get("end_time", "00:00"))) >= end
        for row in relevant
    )


def _active_slot_starts(time_slots: list[dict[str, Any]], day: str, duration: int) -> list[int]:
    active = {
        _minutes(str(row["start_time"]))
        for row in time_slots
        if str(row.get("day_of_week", "")) == day and int(row.get("is_active", 0)) == 1
    }
    starts: list[int] = []
    for start in sorted(active):
        end = start + duration
        if start < SCHOOL_START or end > SCHOOL_END:
            continue
        if all((minute in active) for minute in range(start, end, SLOT_MINUTES)):
            starts.append(start)
    return starts


def _shared_pairs(rows: list[dict[str, Any]]) -> set[tuple[int, int]]:
    pairs: set[tuple[int, int]] = set()
    for row in rows:
        a = int(row.get("section_a", row.get("home_section_id", 0)) or 0)
        b = int(row.get("section_b", row.get("major_section_id", 0)) or 0)
        if a and b and a != b:
            pairs.add((min(a, b), max(a, b)))
    return pairs


def _allowed_days(section_code: str, section_type: str, mode: str) -> set[str]:
    code = str(section_code)
    kind = str(section_type)
    if mode not in {"F2F", "ONLINE"}:
        return set()
    if kind == "MAJOR":
        return set(DAYS)
    if len(code) != 5 or not code.isascii() or not code.isdigit() or int(code[-2:]) < 1:
        return set()
    odd = int(code[-2:]) % 2 == 1
    f2f = MWF if odd else TTHS
    return set(f2f if mode == "F2F" else set(DAYS) - f2f)


def _candidate_conflicts_fixed(candidate: dict[str, Any], fixed: dict[str, Any], shared: set[tuple[int, int]]) -> bool:
    fixed_day = str(fixed.get("day_of_week", ""))
    if candidate["day_of_week"] != fixed_day:
        return False

    fixed_section = int(fixed.get("section_id", 0) or 0)
    pair = None
    if candidate["section_id"] and fixed_section and candidate["section_id"] != fixed_section:
        pair = (min(candidate["section_id"], fixed_section), max(candidate["section_id"], fixed_section))

    # Shared-student mixed delivery on the same day is forbidden even without a time overlap.
    if pair in shared and str(candidate.get("delivery_mode")) != str(fixed.get("delivery_mode")):
        return True

    fs = _minutes(str(fixed.get("start_time", "00:00")))
    fe = _minutes(str(fixed.get("end_time", "00:00")))
    if not _overlap(candidate["start"], candidate["end"], fs, fe):
        return False

    if candidate["teacher_id"] == int(fixed.get("teacher_id", 0) or 0):
        return True
    if candidate["section_id"] == fixed_section:
        return True
    if candidate["subject_id"] == int(fixed.get("subject_id", 0) or 0):
        return True
    if candidate["room_id"] is not None and fixed.get("room_id") is not None:
        if candidate["room_id"] == int(fixed["room_id"]):
            return True
    if pair in shared:
        return True
    return False


def _availability_index(rows: list[dict[str, Any]], id_key: str) -> dict[tuple[int, str], list[tuple[int, int, str]]]:
    index: dict[tuple[int, str], list[tuple[int, int, str]]] = defaultdict(list)
    for row in rows:
        entity_id = int(row.get(id_key, 0) or 0)
        day = str(row.get("day_of_week", ""))
        if entity_id < 1 or day not in DAYS:
            continue
        try:
            start = _minutes(str(row.get("start_time", "00:00")))
            end = _minutes(str(row.get("end_time", "00:00")))
        except Exception:
            continue
        status = str(row.get("status", row.get("availability_status", "AVAILABLE"))).upper()
        index[entity_id, day].append((start, end, status))
    return index


def _indexed_window_allows(
    index: dict[tuple[int, str], list[tuple[int, int, str]]],
    entity_id: int,
    day: str,
    start: int,
    end: int,
) -> bool:
    relevant = index.get((entity_id, day), [])
    if not relevant:
        return False
    for rs, re, status in relevant:
        if status == "UNAVAILABLE" and _overlap(start, end, rs, re):
            return False
    return any(status == "AVAILABLE" and rs <= start and re >= end for rs, re, status in relevant)


def _fixed_conflict_index(
    fixed_meetings: list[dict[str, Any]],
    shared: set[tuple[int, int]],
) -> dict[str, Any]:
    teacher_slots: set[tuple[int, str, int]] = set()
    section_slots: set[tuple[int, str, int]] = set()
    subject_slots: set[tuple[int, str, int]] = set()
    room_slots: set[tuple[int, str, int]] = set()
    section_day_modes: dict[tuple[int, str], set[str]] = defaultdict(set)
    shared_partners: dict[int, set[int]] = defaultdict(set)

    for a, b in shared:
        shared_partners[a].add(b)
        shared_partners[b].add(a)

    for fixed in fixed_meetings:
        day = str(fixed.get("day_of_week", ""))
        if day not in DAYS:
            continue
        try:
            start = _minutes(str(fixed.get("start_time", "00:00")))
            end = _minutes(str(fixed.get("end_time", "00:00")))
        except Exception:
            continue
        if end <= start:
            continue

        section_id = int(fixed.get("section_id", 0) or 0)
        subject_id = int(fixed.get("subject_id", 0) or 0)
        teacher_id = int(fixed.get("teacher_id", 0) or 0)
        room_id = None if fixed.get("room_id") is None else int(fixed.get("room_id"))
        mode = str(fixed.get("delivery_mode", ""))

        if section_id:
            section_day_modes[section_id, day].add(mode)

        for slot in range(start, end, SLOT_MINUTES):
            if section_id:
                section_slots.add((section_id, day, slot))
            if subject_id:
                subject_slots.add((subject_id, day, slot))
            if teacher_id:
                teacher_slots.add((teacher_id, day, slot))
            if room_id is not None:
                room_slots.add((room_id, day, slot))

    return {
        "teacher_slots": teacher_slots,
        "section_slots": section_slots,
        "subject_slots": subject_slots,
        "room_slots": room_slots,
        "section_day_modes": section_day_modes,
        "shared_partners": shared_partners,
    }


def _candidate_conflicts_fixed_indexed(candidate: dict[str, Any], index: dict[str, Any]) -> bool:
    day = str(candidate["day_of_week"])
    section_id = int(candidate["section_id"])
    subject_id = int(candidate["subject_id"])
    teacher_id = int(candidate["teacher_id"])
    room_id = candidate["room_id"]
    mode = str(candidate.get("delivery_mode", ""))

    # Shared-student mixed F2F/ONLINE day is prohibited even when times differ.
    for partner in index["shared_partners"].get(section_id, set()):
        fixed_modes = index["section_day_modes"].get((partner, day), set())
        if any(fixed_mode != mode for fixed_mode in fixed_modes):
            return True

    for slot in range(int(candidate["start"]), int(candidate["end"]), SLOT_MINUTES):
        if (section_id, day, slot) in index["section_slots"]:
            return True
        if (subject_id, day, slot) in index["subject_slots"]:
            return True
        if (teacher_id, day, slot) in index["teacher_slots"]:
            return True
        if room_id is not None and (int(room_id), day, slot) in index["room_slots"]:
            return True
        for partner in index["shared_partners"].get(section_id, set()):
            if (partner, day, slot) in index["section_slots"]:
                return True
    return False


def _build_candidates(
    payload: dict[str, Any],
    movable_ids: set[int] | None = None,
    stage: str = "FULL",
) -> tuple[list[dict[str, Any]], dict[int, list[dict[str, Any]]], set[tuple[int, int]]]:
    all_meetings = payload.get("meetings")
    teachers = payload.get("teachers")
    authorizations = payload.get("teacher_authorizations")
    teacher_availability = payload.get("teacher_availability")
    rooms = payload.get("rooms")
    room_availability = payload.get("room_availability")
    time_slots = payload.get("time_slots")
    other_meetings = payload.get("other_meetings")

    for name, value in {
        "meetings": all_meetings,
        "teachers": teachers,
        "teacher_authorizations": authorizations,
        "teacher_availability": teacher_availability,
        "rooms": rooms,
        "room_availability": room_availability,
        "time_slots": time_slots,
        "other_meetings": other_meetings,
    }.items():
        if not isinstance(value, list):
            raise ValueError(f"{name} must be a list")

    if stage not in {"ROOM_ONLY", "TEACHER_ROOM", "FULL"}:
        raise ValueError(f"Unsupported repair stage: {stage}")
    if not all_meetings:
        raise ValueError("No saved meetings were supplied for repair")

    if movable_ids is None:
        movable_ids = {int(row.get("meeting_id", 0)) for row in all_meetings}
    meetings = [row for row in all_meetings if int(row.get("meeting_id", 0)) in movable_ids]
    if not meetings:
        raise ValueError("No repairable meetings were selected")

    fixed_program_meetings = [
        row for row in all_meetings
        if int(row.get("meeting_id", 0)) not in movable_ids
    ]
    fixed_meetings = list(other_meetings) + fixed_program_meetings

    program_id = int(payload.get("program", {}).get("program_id", 0))
    if program_id < 1:
        raise ValueError("Invalid repair program")

    active_teachers = {
        int(row["teacher_id"]): row
        for row in teachers
        if int(row.get("program_id", 0)) == program_id and str(row.get("status", "ACTIVE")) == "ACTIVE"
    }
    auth: dict[int, set[int]] = defaultdict(set)
    for row in authorizations:
        tid = int(row.get("teacher_id", 0))
        sid = int(row.get("subject_id", 0))
        if tid in active_teachers and sid:
            auth[sid].add(tid)

    usable_rooms: dict[int, dict[str, Any]] = {}
    for row in rooms:
        rid = int(row.get("room_id", 0))
        owner = row.get("program_id")
        if rid < 1 or str(row.get("status", "AVAILABLE")) != "AVAILABLE":
            continue
        if owner is not None and int(owner) != program_id:
            continue
        usable_rooms[rid] = row

    shared = _shared_pairs(payload.get("shared_section_pairs", []))
    fixed_index = _fixed_conflict_index(fixed_meetings, shared)
    teacher_availability_index = _availability_index(teacher_availability, "teacher_id")
    room_availability_index = _availability_index(room_availability, "room_id")
    slot_start_cache: dict[tuple[str, int], list[int]] = {}

    by_meeting: dict[int, list[dict[str, Any]]] = {}
    normalized_meetings: list[dict[str, Any]] = []

    for meeting in meetings:
        mid = int(meeting.get("meeting_id", 0))
        if mid < 1:
            raise ValueError("Meeting without a valid meeting_id")
        mode = str(meeting.get("delivery_mode", ""))
        day = str(meeting.get("day_of_week", ""))
        if mode not in {"F2F", "ONLINE"} or day not in DAYS:
            raise ValueError(f"Saved meeting {mid} has an unsupported day or delivery mode")

        current_start = _minutes(str(meeting.get("start_time", "00:00")))
        current_end = _minutes(str(meeting.get("end_time", "00:00")))
        duration = current_end - current_start
        if duration <= 0 or duration % SLOT_MINUTES:
            raise ValueError(f"Meeting {mid} duration is not a positive 30-minute increment")

        section_id = int(meeting.get("section_id", 0))
        subject_id = int(meeting.get("subject_id", 0))
        section_subject_id = int(meeting.get("section_subject_id", 0))
        capacity_needed = int(meeting.get("section_student_count", 0))
        if min(section_id, subject_id, section_subject_id) < 1:
            raise ValueError(f"Meeting {mid} has an incomplete section/subject reference")

        allowed = _allowed_days(str(meeting.get("section_code", "")), str(meeting.get("section_type", "")), mode)
        if day not in allowed:
            raise ValueError(
                f"Meeting {mid} is on {day}, which violates the current section delivery-day policy. "
                "This repair version preserves weekday; regenerate or use a day-policy repair for that structural issue."
            )

        authorized_teacher_ids = sorted(auth.get(subject_id, set()))
        if not authorized_teacher_ids:
            raise ValueError(f"Subject {subject_id} has no active authorized teacher")

        current_teacher = int(meeting.get("teacher_id", 0) or 0)
        if stage == "ROOM_ONLY":
            teacher_ids = [current_teacher] if current_teacher in auth.get(subject_id, set()) else []
        else:
            teacher_ids = authorized_teacher_ids
        if not teacher_ids:
            raise ValueError(f"No eligible teacher is available for meeting {mid} in stage {stage}")

        cache_key = (day, duration)
        if cache_key not in slot_start_cache:
            slot_start_cache[cache_key] = _active_slot_starts(time_slots, day, duration)
        valid_starts = slot_start_cache[cache_key]
        if stage in {"ROOM_ONLY", "TEACHER_ROOM"}:
            start_options = [current_start] if current_start in valid_starts else []
        else:
            start_options = valid_starts
        if not start_options:
            raise ValueError(f"No valid start exists for meeting {mid} in stage {stage}")

        room_ids: list[int | None]
        if mode == "ONLINE":
            room_ids = [None]
        else:
            room_ids = [
                rid for rid, room in usable_rooms.items()
                if int(room.get("capacity", 0)) >= capacity_needed
            ]
            if not room_ids:
                raise ValueError(f"No compatible room can fit meeting {mid}")

        candidates: list[dict[str, Any]] = []
        for start in start_options:
            end = start + duration
            for teacher_id in teacher_ids:
                if not _indexed_window_allows(teacher_availability_index, teacher_id, day, start, end):
                    continue
                for room_id in room_ids:
                    if room_id is not None and not _indexed_window_allows(room_availability_index, room_id, day, start, end):
                        continue
                    candidate = {
                        "meeting_id": mid,
                        "section_subject_id": section_subject_id,
                        "section_id": section_id,
                        "subject_id": subject_id,
                        "teacher_id": teacher_id,
                        "room_id": room_id,
                        "delivery_mode": mode,
                        "day_of_week": day,
                        "start": start,
                        "end": end,
                        "duration": duration,
                    }
                    if _candidate_conflicts_fixed_indexed(candidate, fixed_index):
                        continue

                    # Minimum disruption objective: room first, teacher second,
                    # time last. Weekday remains protected.
                    cost = 0
                    if teacher_id != current_teacher:
                        cost += 4
                    current_room = None if meeting.get("room_id") is None else int(meeting["room_id"])
                    if room_id != current_room:
                        cost += 1
                    if start != current_start:
                        cost += 8 + abs(start - current_start) // SLOT_MINUTES
                    candidate["cost"] = cost
                    candidates.append(candidate)

        if not candidates:
            raise ValueError(f"No valid repair candidate exists for saved meeting {mid} in stage {stage}")

        candidates.sort(key=lambda c: (c["cost"], abs(c["start"] - current_start), c["teacher_id"], c["room_id"] or 0))
        by_meeting[mid] = candidates
        normalized_meetings.append({
            **meeting,
            "meeting_id": mid,
            "current_start": current_start,
            "current_end": current_end,
            "duration": duration,
        })

    return normalized_meetings, by_meeting, shared

def _add_section_policy_constraints(
    model: cp_model.CpModel,
    meetings: list[dict[str, Any]],
    candidate_vars_by_meeting: dict[int, list[tuple[dict[str, Any], cp_model.IntVar]]],
    program_code: str,
) -> None:
    by_section: dict[int, list[dict[str, Any]]] = defaultdict(list)
    for meeting in meetings:
        by_section[int(meeting["section_id"])].append(meeting)

    for section_id, rows in by_section.items():
        sample = rows[0]
        year = int(sample.get("year_level", 0) or 0)
        kind = str(sample.get("section_type", ""))
        section_code = str(sample.get("section_code", ""))

        f2f_rows = [m for m in rows if str(m["delivery_mode"]) == "F2F"]
        if not f2f_rows:
            continue

        f2f_days = [d for d in DAYS if d in _allowed_days(section_code, kind, "F2F")]
        day_active_vars: list[cp_model.IntVar] = []
        bsoa_y3_states: dict[str, list[cp_model.IntVar]] = {"zero": [], "two": [], "three": []}

        for day in f2f_days:
            presence: list[cp_model.IntVar] = []
            first_vars: list[cp_model.IntVar] = []
            last_vars: list[cp_model.IntVar] = []
            duration_terms: list[Any] = []

            for meeting in f2f_rows:
                mid = int(meeting["meeting_id"])
                day_candidates = [
                    (cand, var) for cand, var in candidate_vars_by_meeting[mid]
                    if cand["day_of_week"] == day
                ]
                p = model.new_bool_var(f"sec{section_id}_m{mid}_{day}_present")
                if day_candidates:
                    model.add(p == sum(var for _, var in day_candidates))
                else:
                    model.add(p == 0)
                presence.append(p)
                duration_terms.append(int(meeting["duration"]) * p)

                start_expr = sum(int(cand["start"]) * var for cand, var in day_candidates) if day_candidates else 0
                end_expr = sum(int(cand["end"]) * var for cand, var in day_candidates) if day_candidates else 0

                first = model.new_int_var(0, SCHOOL_END, f"sec{section_id}_m{mid}_{day}_first")
                last = model.new_int_var(0, SCHOOL_END, f"sec{section_id}_m{mid}_{day}_last")
                model.add(first == start_expr + SCHOOL_END * (1 - p))
                model.add(last == end_expr)
                first_vars.append(first)
                last_vars.append(last)

            count = sum(presence)
            active = model.new_bool_var(f"sec{section_id}_{day}_active")
            day_active_vars.append(active)
            model.add(count >= 1).only_enforce_if(active)
            model.add(count == 0).only_enforce_if(active.Not())

            earliest = model.new_int_var(0, SCHOOL_END, f"sec{section_id}_{day}_earliest")
            latest = model.new_int_var(0, SCHOOL_END, f"sec{section_id}_{day}_latest")
            model.add_min_equality(earliest, first_vars)
            model.add_max_equality(latest, last_vars)
            total_duration = sum(duration_terms)

            if program_code == "BSIT":
                if kind == "REGULAR" and year == 1:
                    model.add(count == 3)
                    model.add(latest - earliest == total_duration + 30)
                elif kind == "REGULAR" and year == 2:
                    three = model.new_bool_var(f"sec{section_id}_{day}_three")
                    model.add(count == 3).only_enforce_if(three)
                    model.add(count == 2).only_enforce_if(three.Not())
                    model.add(latest - earliest == total_duration + 30).only_enforce_if(three)
                    model.add(latest - earliest == total_duration).only_enforce_if(three.Not())
                elif kind == "REGULAR" and year == 3:
                    model.add(count == 3).only_enforce_if(active)
                    model.add(latest - earliest == total_duration + 30).only_enforce_if(active)
                elif kind == "CLUSTER" and year == 4:
                    model.add(count == len(f2f_rows)).only_enforce_if(active)
                    model.add(latest - earliest == total_duration).only_enforce_if(active)
                elif kind == "MAJOR":
                    model.add(count <= 1)
            else:
                # Generic REGULAR policy currently used by BSOA.
                if kind != "REGULAR":
                    continue
                if year == 3:
                    zero = model.new_bool_var(f"sec{section_id}_{day}_zero")
                    two = model.new_bool_var(f"sec{section_id}_{day}_two")
                    three = model.new_bool_var(f"sec{section_id}_{day}_three")
                    model.add_exactly_one(zero, two, three)
                    model.add(count == 0).only_enforce_if(zero)
                    model.add(count == 2).only_enforce_if(two)
                    model.add(count == 3).only_enforce_if(three)
                    bsoa_y3_states["zero"].append(zero)
                    bsoa_y3_states["two"].append(two)
                    bsoa_y3_states["three"].append(three)
                elif year == 4:
                    model.add(count == len(f2f_rows)).only_enforce_if(active)
                else:
                    low, remainder = divmod(len(f2f_rows), len(f2f_days))
                    high = low + (1 if remainder else 0)
                    model.add(count >= low)
                    model.add(count <= high)

                if year in (1, 2, 3):
                    three = model.new_bool_var(f"sec{section_id}_{day}_break_three")
                    model.add(count == 3).only_enforce_if(three)
                    model.add(count != 3).only_enforce_if(three.Not())
                    model.add(latest - earliest == total_duration + 30).only_enforce_if(three)
                    model.add(latest - earliest == total_duration).only_enforce_if([active, three.Not()])
                elif year == 4:
                    model.add(latest - earliest == total_duration).only_enforce_if(active)

        if program_code == "BSIT":
            if kind == "REGULAR" and year == 3:
                # Two active F2F days for six subjects.
                model.add(sum(day_active_vars) == 2)
            if kind == "CLUSTER" and year == 4:
                model.add(sum(day_active_vars) == 1)
        else:
            if kind == "REGULAR" and year == 3 and bsoa_y3_states["zero"]:
                model.add(sum(bsoa_y3_states["zero"]) == 1)
                model.add(sum(bsoa_y3_states["two"]) == 1)
                model.add(sum(bsoa_y3_states["three"]) == 1)
            if kind == "REGULAR" and year == 4:
                model.add(sum(day_active_vars) == 1)


def _audit_assignments(payload: dict[str, Any], assignments: list[dict[str, Any]]) -> dict[str, Any]:
    old_rows = payload.get("meetings", [])
    if len(old_rows) != len(assignments):
        return {"passed": False, "status": "MEETING_COUNT_CHANGED", "errors": ["Repair changed meeting count."]}

    old_by_id = {int(m["meeting_id"]): m for m in old_rows}
    new_by_id = {int(m["meeting_id"]): m for m in assignments}
    if set(old_by_id) != set(new_by_id):
        return {"passed": False, "status": "MEETING_IDENTITY_CHANGED", "errors": ["Repair changed meeting identity."]}

    errors: list[str] = []
    program_id = int(payload.get("program", {}).get("program_id", 0))
    program_code = str(payload.get("program", {}).get("program_code", "")).upper()
    teacher_rows = {int(t["teacher_id"]): t for t in payload.get("teachers", [])}
    room_rows = {int(r["room_id"]): r for r in payload.get("rooms", [])}
    auth = {(int(a["teacher_id"]), int(a["subject_id"])) for a in payload.get("teacher_authorizations", [])}
    shared = _shared_pairs(payload.get("shared_section_pairs", []))

    normalized: list[dict[str, Any]] = []
    teacher_daily: dict[tuple[int, str], int] = defaultdict(int)
    teacher_weekly: dict[int, int] = defaultdict(int)
    rooms_by_section: dict[int, set[int]] = defaultdict(set)
    teachers_by_ss: dict[int, set[int]] = defaultdict(set)

    time_slots = payload.get("time_slots", [])

    for mid in sorted(new_by_id):
        old = old_by_id[mid]
        new = new_by_id[mid]
        for key in ("section_subject_id", "section_id", "subject_id", "delivery_mode", "day_of_week"):
            if str(new.get(key)) != str(old.get(key)):
                errors.append(f"Meeting {mid} changed protected field {key}.")

        start = _minutes(str(new.get("start_time", "00:00")))
        end = _minutes(str(new.get("end_time", "00:00")))
        old_duration = _minutes(str(old.get("end_time", "00:00"))) - _minutes(str(old.get("start_time", "00:00")))
        day = str(new.get("day_of_week", ""))
        if end - start != old_duration or start < SCHOOL_START or end > SCHOOL_END:
            errors.append(f"Meeting {mid} changed duration or left school hours.")
        if start % SLOT_MINUTES or end % SLOT_MINUTES:
            errors.append(f"Meeting {mid} is not aligned to the 30-minute grid.")
        active_starts = _active_slot_starts(time_slots, day, old_duration)
        if start not in active_starts:
            errors.append(f"Meeting {mid} does not use consecutive active database time slots.")

        allowed = _allowed_days(str(old.get("section_code", "")), str(old.get("section_type", "")), str(new.get("delivery_mode", "")))
        if day not in allowed:
            errors.append(f"Meeting {mid} violates section delivery-day policy.")

        teacher = int(new.get("teacher_id", 0))
        subject = int(new.get("subject_id", 0))
        teacher_row = teacher_rows.get(teacher)
        if teacher_row is None or int(teacher_row.get("program_id", 0)) != program_id or str(teacher_row.get("status", "ACTIVE")) != "ACTIVE":
            errors.append(f"Meeting {mid} has an invalid or inactive teacher.")
        if (teacher, subject) not in auth:
            errors.append(f"Meeting {mid} teacher is not authorized for the subject.")
        if not _window_allows(payload.get("teacher_availability", []), teacher, day, start, end, "teacher_id"):
            errors.append(f"Meeting {mid} is outside teacher availability.")

        teacher_daily[teacher, day] += end - start
        teacher_weekly[teacher] += end - start
        teachers_by_ss[int(new.get("section_subject_id", 0))].add(teacher)

        room = new.get("room_id")
        if str(new.get("delivery_mode")) == "ONLINE":
            if room is not None:
                errors.append(f"Online meeting {mid} has a physical room.")
        else:
            if room is None or int(room) not in room_rows:
                errors.append(f"F2F meeting {mid} has no valid room.")
            else:
                room_id = int(room)
                rr = room_rows[room_id]
                owner = rr.get("program_id")
                if owner is not None and int(owner) != program_id:
                    errors.append(f"Meeting {mid} uses another program's room.")
                if int(rr.get("capacity", 0)) < int(old.get("section_student_count", 0)):
                    errors.append(f"Meeting {mid} exceeds room capacity.")
                if str(rr.get("status", "AVAILABLE")) != "AVAILABLE":
                    errors.append(f"Meeting {mid} uses an unavailable room.")
                if not _window_allows(payload.get("room_availability", []), room_id, day, start, end, "room_id"):
                    errors.append(f"Meeting {mid} is outside room availability.")
                rooms_by_section[int(new.get("section_id", 0))].add(room_id)

        normalized.append({
            **new,
            "start": start,
            "end": end,
            "section_type": str(old.get("section_type", "")),
            "year_level": int(old.get("year_level", 0) or 0),
            "section_code": str(old.get("section_code", "")),
        })

    for ssid, tids in teachers_by_ss.items():
        if len(tids) > 1:
            errors.append(f"Section-subject {ssid} has inconsistent F2F/Online teachers.")

    for section_id, room_ids in rooms_by_section.items():
        if len(room_ids) > 1:
            errors.append(f"Section {section_id} has inconsistent F2F rooms.")

    for teacher_id, row in teacher_rows.items():
        max_week = round(float(row.get("max_weekly_hours", 0)) * 60)
        max_day = round(float(row.get("max_daily_hours", 0)) * 60)
        if teacher_weekly[teacher_id] > max_week:
            errors.append(f"Teacher {teacher_id} weekly workload exceeded.")
        for day in DAYS:
            if teacher_daily[teacher_id, day] > max_day:
                errors.append(f"Teacher {teacher_id} {day} daily workload exceeded.")

    # Internal overlap checks.
    for field in ("section_id", "subject_id", "teacher_id", "room_id"):
        grouped: dict[tuple[Any, str], list[dict[str, Any]]] = defaultdict(list)
        for row in normalized:
            value = row.get(field)
            if value is not None:
                grouped[value, str(row["day_of_week"])].append(row)
        for (value, day), rows in grouped.items():
            rows.sort(key=lambda r: (r["start"], r["end"]))
            for i, first in enumerate(rows):
                for second in rows[i + 1:]:
                    if second["start"] >= first["end"]:
                        break
                    if _overlap(first["start"], first["end"], second["start"], second["end"]):
                        errors.append(f"{field} overlap {value} on {day}: meetings {first['meeting_id']} and {second['meeting_id']}.")

    # Other ACTIVE programs are immutable fixed facts.
    for row in normalized:
        cand = {
            "section_id": int(row.get("section_id", 0)),
            "subject_id": int(row.get("subject_id", 0)),
            "teacher_id": int(row.get("teacher_id", 0)),
            "room_id": None if row.get("room_id") is None else int(row.get("room_id")),
            "delivery_mode": str(row.get("delivery_mode", "")),
            "day_of_week": str(row.get("day_of_week", "")),
            "start": int(row["start"]),
            "end": int(row["end"]),
        }
        for fixed in payload.get("other_meetings", []):
            if _candidate_conflicts_fixed(cand, fixed, shared):
                errors.append(f"Meeting {row['meeting_id']} conflicts with saved meeting {fixed.get('meeting_id')} from another ACTIVE program.")

    # Shared-student pair constraints.
    by_section: dict[int, list[dict[str, Any]]] = defaultdict(list)
    for row in normalized:
        by_section[int(row["section_id"])].append(row)
    for a, b in shared:
        for left in by_section.get(a, []):
            for right in by_section.get(b, []):
                if left["day_of_week"] != right["day_of_week"]:
                    continue
                if str(left["delivery_mode"]) != str(right["delivery_mode"]):
                    errors.append(f"Shared-student mixed delivery day: sections {a} and {b} on {left['day_of_week']}.")
                if _overlap(left["start"], left["end"], right["start"], right["end"]):
                    errors.append(f"Shared-student overlap: sections {a} and {b} on {left['day_of_week']}.")

    # Daily F2F count/break policy. Weekday is protected, so distribution should
    # remain identical, but the repair may move a class within that day.
    for section_id, rows in by_section.items():
        sample = rows[0]
        year = int(sample["year_level"])
        kind = str(sample["section_type"])
        section_code = str(sample["section_code"])
        f2f_days = [d for d in DAYS if d in _allowed_days(section_code, kind, "F2F")]
        f2f = [r for r in rows if str(r["delivery_mode"]) == "F2F"]
        counts = []
        for day in f2f_days:
            daily = sorted((r for r in f2f if r["day_of_week"] == day), key=lambda r: r["start"])
            count = len(daily)
            counts.append(count)
            if program_code == "BSIT":
                if kind == "REGULAR" and year == 1 and count != 3:
                    errors.append(f"Section {section_code} {day}: expected 3 F2F meetings.")
                elif kind == "REGULAR" and year == 2 and count not in (2, 3):
                    errors.append(f"Section {section_code} {day}: expected 2 or 3 F2F meetings.")
                elif kind == "REGULAR" and year == 3 and count not in (0, 3):
                    errors.append(f"Section {section_code} {day}: expected 0 or 3 F2F meetings.")
                elif kind == "CLUSTER" and year == 4 and count not in (0, len(f2f)):
                    errors.append(f"Cluster {section_code}: F2F cluster meetings must remain on one day.")
                elif kind == "MAJOR" and count > 1:
                    errors.append(f"Major {section_code} {day}: more than one major F2F meeting.")
            else:
                if kind == "REGULAR" and year == 3 and count not in (0, 2, 3):
                    errors.append(f"Section {section_code} {day}: invalid 3rd-year F2F count.")
                elif kind == "REGULAR" and year == 4 and count not in (0, len(f2f)):
                    errors.append(f"Section {section_code}: 4th-year F2F meetings must remain on one day.")
                elif kind == "REGULAR" and year in (1, 2):
                    low, rem = divmod(len(f2f), len(f2f_days))
                    high = low + (1 if rem else 0)
                    if count < low or count > high:
                        errors.append(f"Section {section_code} {day}: unbalanced F2F count.")

            if daily:
                span = daily[-1]["end"] - daily[0]["start"]
                total = sum(r["end"] - r["start"] for r in daily)
                expected_gap = 30 if kind == "REGULAR" and year in (1, 2, 3) and count == 3 else 0
                if span != total + expected_gap:
                    errors.append(f"Section {section_code} {day}: F2F break/gap policy violated.")

        if program_code != "BSIT" and kind == "REGULAR" and year == 3 and sorted(counts) != [0, 2, 3]:
            errors.append(f"Section {section_code}: expected BSOA 3rd-year [0,2,3] F2F distribution.")

    return {
        "passed": not errors,
        "status": "REPAIR_AUDIT_PASSED" if not errors else "REPAIR_AUDIT_FAILED",
        "errors": list(dict.fromkeys(errors)),
        "checked_meetings": len(assignments),
        "database_write": False,
    }


def _current_assignment_rows(payload: dict[str, Any]) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    for meeting in payload.get("meetings", []):
        rows.append({
            "meeting_id": int(meeting.get("meeting_id", 0)),
            "section_subject_id": int(meeting.get("section_subject_id", 0)),
            "section_id": int(meeting.get("section_id", 0)),
            "subject_id": int(meeting.get("subject_id", 0)),
            "teacher_id": int(meeting.get("teacher_id", 0)),
            "room_id": None if meeting.get("room_id") is None else int(meeting.get("room_id")),
            "delivery_mode": str(meeting.get("delivery_mode", "")),
            "day_of_week": str(meeting.get("day_of_week", "")),
            "start_time": str(meeting.get("start_time", ""))[:5],
            "end_time": str(meeting.get("end_time", ""))[:5],
        })
    return rows


def _direct_problem_meeting_ids(payload: dict[str, Any]) -> set[int]:
    """Find the meetings that are actually participating in current hard conflicts.

    This is intentionally conservative. It detects resource overlaps, invalid
    assignments/availability, consistency problems, workload violations, and
    conflicts against immutable ACTIVE schedules from other programs.
    """
    meetings = list(payload.get("meetings", []))
    bad: set[int] = set()
    shared = _shared_pairs(payload.get("shared_section_pairs", []))
    program_id = int(payload.get("program", {}).get("program_id", 0))

    normalized: list[dict[str, Any]] = []
    for row in meetings:
        try:
            normalized.append({
                **row,
                "meeting_id": int(row.get("meeting_id", 0)),
                "section_id": int(row.get("section_id", 0)),
                "subject_id": int(row.get("subject_id", 0)),
                "section_subject_id": int(row.get("section_subject_id", 0)),
                "teacher_id": int(row.get("teacher_id", 0)),
                "room_id": None if row.get("room_id") is None else int(row.get("room_id")),
                "start": _minutes(str(row.get("start_time", "00:00"))),
                "end": _minutes(str(row.get("end_time", "00:00"))),
            })
        except Exception:
            mid = int(row.get("meeting_id", 0) or 0)
            if mid:
                bad.add(mid)

    # Current internal overlap conflicts.
    for i, left in enumerate(normalized):
        for right in normalized[i + 1:]:
            if left["day_of_week"] != right["day_of_week"]:
                continue
            if not _overlap(left["start"], left["end"], right["start"], right["end"]):
                continue
            pair = None
            if left["section_id"] != right["section_id"]:
                pair = (min(left["section_id"], right["section_id"]), max(left["section_id"], right["section_id"]))
            conflict = (
                left["section_id"] == right["section_id"]
                or left["subject_id"] == right["subject_id"]
                or left["teacher_id"] == right["teacher_id"]
                or (
                    left["room_id"] is not None
                    and right["room_id"] is not None
                    and left["room_id"] == right["room_id"]
                )
                or pair in shared
            )
            if conflict:
                bad.update((left["meeting_id"], right["meeting_id"]))

    teacher_rows = {int(t["teacher_id"]): t for t in payload.get("teachers", [])}
    room_rows = {int(r["room_id"]): r for r in payload.get("rooms", [])}
    auth = {(int(a["teacher_id"]), int(a["subject_id"])) for a in payload.get("teacher_authorizations", [])}
    time_slots = payload.get("time_slots", [])

    teacher_daily: dict[tuple[int, str], int] = defaultdict(int)
    teacher_weekly: dict[int, int] = defaultdict(int)
    meetings_by_teacher: dict[int, list[int]] = defaultdict(list)
    rooms_by_section: dict[int, set[int]] = defaultdict(set)
    meetings_by_section: dict[int, list[int]] = defaultdict(list)
    teachers_by_ss: dict[int, set[int]] = defaultdict(set)
    meetings_by_ss: dict[int, list[int]] = defaultdict(list)

    for row in normalized:
        mid = row["meeting_id"]
        day = str(row.get("day_of_week", ""))
        mode = str(row.get("delivery_mode", ""))
        start, end = row["start"], row["end"]
        duration = end - start
        teacher_id = row["teacher_id"]
        subject_id = row["subject_id"]
        section_id = row["section_id"]
        ssid = row["section_subject_id"]
        room_id = row["room_id"]

        meetings_by_teacher[teacher_id].append(mid)
        meetings_by_section[section_id].append(mid)
        meetings_by_ss[ssid].append(mid)
        teachers_by_ss[ssid].add(teacher_id)
        teacher_daily[teacher_id, day] += max(duration, 0)
        teacher_weekly[teacher_id] += max(duration, 0)

        teacher = teacher_rows.get(teacher_id)
        if (
            teacher is None
            or int(teacher.get("program_id", 0)) != program_id
            or str(teacher.get("status", "ACTIVE")) != "ACTIVE"
            or (teacher_id, subject_id) not in auth
            or not _window_allows(payload.get("teacher_availability", []), teacher_id, day, start, end, "teacher_id")
        ):
            bad.add(mid)

        if duration <= 0 or start not in _active_slot_starts(time_slots, day, duration):
            bad.add(mid)

        if mode == "ONLINE":
            if room_id is not None:
                bad.add(mid)
        elif mode == "F2F":
            if room_id is None or room_id not in room_rows:
                bad.add(mid)
            else:
                room = room_rows[room_id]
                owner = room.get("program_id")
                if (
                    (owner is not None and int(owner) != program_id)
                    or str(room.get("status", "AVAILABLE")) != "AVAILABLE"
                    or int(room.get("capacity", 0)) < int(row.get("section_student_count", 0) or 0)
                    or not _window_allows(payload.get("room_availability", []), room_id, day, start, end, "room_id")
                ):
                    bad.add(mid)
                rooms_by_section[section_id].add(room_id)

        current = {
            "section_id": section_id,
            "subject_id": subject_id,
            "teacher_id": teacher_id,
            "room_id": room_id,
            "delivery_mode": mode,
            "day_of_week": day,
            "start": start,
            "end": end,
        }
        for fixed in payload.get("other_meetings", []):
            if _candidate_conflicts_fixed(current, fixed, shared):
                bad.add(mid)
                break

    for section_id, room_ids in rooms_by_section.items():
        if len(room_ids) > 1:
            bad.update(meetings_by_section[section_id])
    for ssid, teachers in teachers_by_ss.items():
        if len(teachers) > 1:
            bad.update(meetings_by_ss[ssid])

    for teacher_id, teacher in teacher_rows.items():
        max_week = round(float(teacher.get("max_weekly_hours", 0)) * 60)
        max_day = round(float(teacher.get("max_daily_hours", 0)) * 60)
        overloaded = teacher_weekly[teacher_id] > max_week
        overloaded = overloaded or any(teacher_daily[teacher_id, day] > max_day for day in DAYS)
        if overloaded:
            bad.update(meetings_by_teacher[teacher_id])

    # Reuse the independent audit as a structural detector too. This lets the
    # localized solver react to section-policy / consistency errors without
    # making the whole program movable.
    audit = _audit_assignments(payload, _current_assignment_rows(payload))
    if not audit.get("passed", False):
        by_code: dict[str, set[int]] = defaultdict(set)
        by_section_id: dict[int, set[int]] = defaultdict(set)
        by_ssid: dict[int, set[int]] = defaultdict(set)
        by_teacher_id: dict[int, set[int]] = defaultdict(set)
        for row in normalized:
            by_code[str(row.get("section_code", ""))].add(row["meeting_id"])
            by_section_id[row["section_id"]].add(row["meeting_id"])
            by_ssid[row["section_subject_id"]].add(row["meeting_id"])
            by_teacher_id[row["teacher_id"]].add(row["meeting_id"])
        import re
        for error in audit.get("errors", []):
            for m in re.findall(r"Meeting (\d+)", str(error)):
                bad.add(int(m))
            for a, b in re.findall(r"meetings (\d+) and (\d+)", str(error), flags=re.I):
                bad.update((int(a), int(b)))
            for code in re.findall(r"Section ([A-Za-z0-9_-]+)", str(error)):
                bad.update(by_code.get(code, set()))
            for ssid in re.findall(r"Section-subject (\d+)", str(error)):
                bad.update(by_ssid.get(int(ssid), set()))
            for tid in re.findall(r"Teacher (\d+)", str(error)):
                bad.update(by_teacher_id.get(int(tid), set()))
            for a, b in re.findall(r"sections (\d+) and (\d+)", str(error), flags=re.I):
                bad.update(by_section_id.get(int(a), set()))
                bad.update(by_section_id.get(int(b), set()))

    return {mid for mid in bad if mid > 0}


def _expand_repair_neighborhood(payload: dict[str, Any], seed_ids: set[int]) -> set[int]:
    """Repair full affected sections, but keep every unrelated section locked."""
    meetings = list(payload.get("meetings", []))
    section_by_mid = {int(m.get("meeting_id", 0)): int(m.get("section_id", 0)) for m in meetings}
    affected_sections = {section_by_mid[mid] for mid in seed_ids if section_by_mid.get(mid, 0) > 0}
    movable = {
        int(m.get("meeting_id", 0))
        for m in meetings
        if int(m.get("section_id", 0)) in affected_sections
    }
    return {mid for mid in movable if mid > 0}


def _solve_repair_stage(
    payload: dict[str, Any],
    movable_ids: set[int],
    stage: str,
) -> dict[str, Any]:
    all_meetings = list(payload.get("meetings", []))
    meetings, candidates_by_meeting, shared = _build_candidates(payload, movable_ids, stage)
    model = cp_model.CpModel()

    candidate_vars_by_meeting: dict[int, list[tuple[dict[str, Any], cp_model.IntVar]]] = defaultdict(list)
    candidate_vars: list[tuple[dict[str, Any], cp_model.IntVar]] = []
    resource_slots: dict[tuple[Any, ...], list[cp_model.IntVar]] = defaultdict(list)

    for meeting in meetings:
        mid = int(meeting["meeting_id"])
        vars_here: list[cp_model.IntVar] = []
        for idx, cand in enumerate(candidates_by_meeting[mid]):
            var = model.new_bool_var(f"{stage}_m{mid}_c{idx}")
            vars_here.append(var)
            candidate_vars.append((cand, var))
            candidate_vars_by_meeting[mid].append((cand, var))
            for slot in range(cand["start"], cand["end"], SLOT_MINUTES):
                day = cand["day_of_week"]
                resource_slots[("SECTION", cand["section_id"], day, slot)].append(var)
                resource_slots[("SUBJECT", cand["subject_id"], day, slot)].append(var)
                resource_slots[("TEACHER", cand["teacher_id"], day, slot)].append(var)
                if cand["room_id"] is not None:
                    resource_slots[("ROOM", cand["room_id"], day, slot)].append(var)
                for a, b in shared:
                    if cand["section_id"] in (a, b):
                        resource_slots[("SHARED", a, b, day, slot)].append(var)
        model.add_exactly_one(vars_here)

    for vars_here in resource_slots.values():
        if len(vars_here) > 1:
            model.add(sum(vars_here) <= 1)

    # One teacher per section-subject across F2F + ONLINE.
    meetings_by_ss: dict[int, list[int]] = defaultdict(list)
    for meeting in meetings:
        meetings_by_ss[int(meeting["section_subject_id"])].append(int(meeting["meeting_id"]))
    for ssid, mids in meetings_by_ss.items():
        if len(mids) < 2:
            continue
        teachers = sorted({cand["teacher_id"] for mid in mids for cand in candidates_by_meeting[mid]})
        choice = {tid: model.new_bool_var(f"{stage}_ss{ssid}_teacher{tid}") for tid in teachers}
        model.add_exactly_one(list(choice.values()))
        for mid in mids:
            for cand, var in candidate_vars_by_meeting[mid]:
                model.add(var <= choice[cand["teacher_id"]])

    # One physical F2F room per section, matching the normal scheduler rule.
    f2f_by_section: dict[int, list[int]] = defaultdict(list)
    for meeting in meetings:
        if str(meeting["delivery_mode"]) == "F2F":
            f2f_by_section[int(meeting["section_id"])].append(int(meeting["meeting_id"]))
    for section_id, mids in f2f_by_section.items():
        rooms = sorted({
            int(cand["room_id"])
            for mid in mids
            for cand in candidates_by_meeting[mid]
            if cand["room_id"] is not None
        })
        if not rooms:
            raise ValueError(f"Section {section_id} has no eligible physical room")
        choice = {rid: model.new_bool_var(f"{stage}_section{section_id}_room{rid}") for rid in rooms}
        model.add_exactly_one(list(choice.values()))
        for mid in mids:
            for cand, var in candidate_vars_by_meeting[mid]:
                if cand["room_id"] is not None:
                    model.add(var <= choice[int(cand["room_id"])])

    # Teacher daily + weekly workload remains hard. Unaffected meetings are fixed.
    teacher_rows = {int(t["teacher_id"]): t for t in payload.get("teachers", [])}
    fixed_daily: dict[tuple[int, str], int] = defaultdict(int)
    fixed_weekly: dict[int, int] = defaultdict(int)
    for row in all_meetings:
        if int(row.get("meeting_id", 0)) in movable_ids:
            continue
        tid = int(row.get("teacher_id", 0) or 0)
        day = str(row.get("day_of_week", ""))
        duration = _minutes(str(row.get("end_time", "00:00"))) - _minutes(str(row.get("start_time", "00:00")))
        fixed_daily[tid, day] += max(duration, 0)
        fixed_weekly[tid] += max(duration, 0)

    for teacher_id, teacher in teacher_rows.items():
        weekly_terms: list[Any] = []
        for day in DAYS:
            daily_terms: list[Any] = []
            for cand, var in candidate_vars:
                if cand["teacher_id"] == teacher_id:
                    term = int(cand["duration"]) * var
                    weekly_terms.append(term)
                    if cand["day_of_week"] == day:
                        daily_terms.append(term)
            max_day = round(float(teacher.get("max_daily_hours", 0)) * 60)
            if daily_terms:
                model.add(fixed_daily[teacher_id, day] + sum(daily_terms) <= max_day)
            elif fixed_daily[teacher_id, day] > max_day:
                raise ValueError(
                    f"Teacher {teacher_id} already exceeds the {day} daily workload outside the repair neighborhood"
                )
        max_week = round(float(teacher.get("max_weekly_hours", 0)) * 60)
        if weekly_terms:
            model.add(fixed_weekly[teacher_id] + sum(weekly_terms) <= max_week)
        elif fixed_weekly[teacher_id] > max_week:
            raise ValueError(f"Teacher {teacher_id} already exceeds weekly workload outside the repair neighborhood")

    # Preserve normal section daily-count and break/gap policy.
    _add_section_policy_constraints(
        model,
        meetings,
        candidate_vars_by_meeting,
        str(payload.get("program", {}).get("program_code", "")).upper(),
    )

    model.minimize(sum(int(cand["cost"]) * var for cand, var in candidate_vars))

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = MAX_REPAIR_SECONDS
    solver.parameters.num_search_workers = 8
    status = solver.solve(model)

    result: dict[str, Any] = {
        "stage": stage,
        "solver_status": solver.status_name(status),
        "candidate_count": len(candidate_vars),
        "movable_meetings": len(meetings),
    }
    if status not in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        result["feasible"] = False
        return result

    chosen: dict[int, dict[str, Any]] = {}
    for cand, var in candidate_vars:
        if solver.value(var):
            chosen[int(cand["meeting_id"])] = cand

    result.update({
        "feasible": True,
        "chosen": chosen,
        "objective_cost": int(round(solver.objective_value)),
    })
    return result


def solve_conflict_repair(payload: dict[str, Any]) -> dict[str, Any]:
    all_meetings = list(payload.get("meetings", []))
    seed_ids = _direct_problem_meeting_ids(payload)
    if not seed_ids:
        current_audit = _audit_assignments(payload, _current_assignment_rows(payload))
        if current_audit.get("passed", False):
            return {
                "success": True,
                "status": "NO_CONFLICTS_TO_REPAIR",
                "message": "The selected saved timetable already passes the repair audit.",
                "assignments": _current_assignment_rows(payload),
                "changes": [],
                "audit": current_audit,
                "database_write": False,
            }
        raise ValueError("The conflict is structural but could not be localized safely. No saved meeting was changed.")

    movable_ids = _expand_repair_neighborhood(payload, seed_ids)

    # Progressive repair keeps the first attempt tiny:
    # 1) ROOM_ONLY: preserve teacher and time, change physical room only.
    # 2) TEACHER_ROOM: preserve time, allow authorized teacher + room changes.
    # 3) FULL: preserve weekday but allow teacher + room + time changes.
    # Every stage keeps the same hard constraints and runs against all locked meetings.
    stage_attempts: list[dict[str, Any]] = []
    chosen: dict[int, dict[str, Any]] | None = None
    chosen_stage = ""
    chosen_objective = 0

    for stage in ("ROOM_ONLY", "TEACHER_ROOM", "FULL"):
        try:
            attempt = _solve_repair_stage(payload, movable_ids, stage)
        except ValueError as error:
            stage_attempts.append({
                "stage": stage,
                "feasible": False,
                "solver_status": "NOT_RUN",
                "candidate_count": 0,
                "message": str(error),
            })
            continue

        stage_attempts.append({
            "stage": stage,
            "feasible": bool(attempt.get("feasible")),
            "solver_status": str(attempt.get("solver_status", "UNKNOWN")),
            "candidate_count": int(attempt.get("candidate_count", 0)),
            "movable_meetings": int(attempt.get("movable_meetings", 0)),
        })
        if attempt.get("feasible"):
            chosen = attempt["chosen"]
            chosen_stage = stage
            chosen_objective = int(attempt.get("objective_cost", 0))
            break

    if chosen is None:
        return {
            "success": False,
            "status": "NO_CONFLICT_FREE_REPAIR",
            "message": (
                "Google OR-Tools could not find a safe repair in the progressive room-only, "
                "teacher/room, or full same-weekday repair stages while preserving all hard constraints."
            ),
            "stage_attempts": stage_attempts,
            "database_write": False,
        }

    assignments: list[dict[str, Any]] = []
    changes: list[dict[str, Any]] = []
    before_by_id = {int(m["meeting_id"]): m for m in all_meetings}

    # Unaffected timetable rows remain locked and are included in the final audit.
    for before in all_meetings:
        mid = int(before.get("meeting_id", 0))
        if mid in movable_ids:
            continue
        assignments.append({
            "meeting_id": mid,
            "section_subject_id": int(before.get("section_subject_id", 0)),
            "section_id": int(before.get("section_id", 0)),
            "subject_id": int(before.get("subject_id", 0)),
            "teacher_id": int(before.get("teacher_id", 0)),
            "room_id": None if before.get("room_id") is None else int(before.get("room_id")),
            "delivery_mode": str(before.get("delivery_mode", "")),
            "day_of_week": str(before.get("day_of_week", "")),
            "start_time": str(before.get("start_time", ""))[:5],
            "end_time": str(before.get("end_time", ""))[:5],
        })

    teacher_name_by_id = {int(t["teacher_id"]): t.get("teacher_name") for t in payload.get("teachers", [])}
    room_name_by_id = {int(r["room_id"]): r.get("room_name") for r in payload.get("rooms", [])}

    for mid in sorted(chosen):
        cand = chosen[mid]
        before = before_by_id[mid]
        item = {
            "meeting_id": mid,
            "section_subject_id": cand["section_subject_id"],
            "section_id": cand["section_id"],
            "subject_id": cand["subject_id"],
            "teacher_id": cand["teacher_id"],
            "room_id": cand["room_id"],
            "delivery_mode": cand["delivery_mode"],
            "day_of_week": cand["day_of_week"],
            "start_time": _clock(cand["start"]),
            "end_time": _clock(cand["end"]),
        }
        assignments.append(item)

        fields: list[str] = []
        if int(before.get("teacher_id", 0)) != cand["teacher_id"]:
            fields.append("teacher")
        before_room = None if before.get("room_id") is None else int(before["room_id"])
        if before_room != cand["room_id"]:
            fields.append("room")
        if _minutes(str(before.get("start_time", "00:00"))) != cand["start"]:
            fields.append("time")

        if fields:
            changes.append({
                "meeting_id": mid,
                "section_code": before.get("section_code"),
                "subject_code": before.get("subject_code"),
                "delivery_mode": cand["delivery_mode"],
                "fields_changed": fields,
                "before": {
                    "teacher_id": int(before.get("teacher_id", 0)),
                    "teacher_name": before.get("teacher_name"),
                    "room_id": before_room,
                    "room_name": before.get("room_name"),
                    "day_of_week": cand["day_of_week"],
                    "start_time": str(before.get("start_time", ""))[:5],
                    "end_time": str(before.get("end_time", ""))[:5],
                },
                "after": {
                    "teacher_id": cand["teacher_id"],
                    "teacher_name": teacher_name_by_id.get(cand["teacher_id"]),
                    "room_id": cand["room_id"],
                    "room_name": None if cand["room_id"] is None else room_name_by_id.get(cand["room_id"]),
                    "day_of_week": cand["day_of_week"],
                    "start_time": _clock(cand["start"]),
                    "end_time": _clock(cand["end"]),
                },
            })

    assignments.sort(key=lambda row: int(row["meeting_id"]))
    audit = _audit_assignments(payload, assignments)
    if not audit["passed"]:
        return {
            "success": False,
            "status": "REPAIR_AUDIT_FAILED",
            "message": "The proposed repair did not pass the independent hard-constraint audit.",
            "audit": audit,
            "repair_stage": chosen_stage,
            "stage_attempts": stage_attempts,
            "database_write": False,
        }

    changed_field_counts = defaultdict(int)
    for change in changes:
        for field in change.get("fields_changed", []):
            changed_field_counts[field] += 1

    successful_attempt = next((a for a in stage_attempts if a.get("stage") == chosen_stage), {})

    return {
        "success": True,
        "status": "CONFLICT_REPAIR_PREVIEW_READY",
        "program": payload.get("program", {}),
        "period": payload.get("period", {}),
        "batch_id": int(payload.get("batch_id", 0)),
        "saved_meetings": len(all_meetings),
        "repair_scope": "LOCALIZED_PROGRESSIVE_CONFLICT_NEIGHBORHOOD",
        "repair_stage": chosen_stage,
        "seed_conflict_meeting_ids": sorted(seed_ids),
        "movable_meeting_ids": sorted(movable_ids),
        "movable_meetings": len(movable_ids),
        "candidate_count": int(successful_attempt.get("candidate_count", 0)),
        "stage_attempts": stage_attempts,
        "changed_meetings": len(changes),
        "unchanged_meetings": len(all_meetings) - len(changes),
        "changed_fields": dict(changed_field_counts),
        "objective_cost": chosen_objective,
        "assignments": assignments,
        "changes": changes,
        "audit": audit,
        "database_write": False,
        "repair_policy": "LOCALIZED_PROGRESSIVE_MINIMUM_CHANGE_SAME_WEEKDAY",
        "protected_fields": ["meeting_id", "batch_id", "section_subject_id", "section_id", "subject_id", "delivery_mode", "day_of_week"],
        "repairable_fields": ["teacher_id", "room_id", "start_time", "end_time"],
    }


@router.post("/repair")
def repair(payload: dict = Body(...)):
    try:
        return solve_conflict_repair(payload)
    except ValueError as error:
        raise HTTPException(status_code=422, detail=str(error))
    except Exception as error:
        raise HTTPException(status_code=500, detail=f"Conflict repair failed: {type(error).__name__}: {error}")


@router.post("/repair/audit")
def repair_audit(payload: dict = Body(...)):
    try:
        source = payload.get("input")
        assignments = payload.get("assignments")
        if not isinstance(source, dict) or not isinstance(assignments, list):
            raise ValueError("input and assignments are required")
        return _audit_assignments(source, assignments)
    except ValueError as error:
        raise HTTPException(status_code=422, detail=str(error))
    except Exception as error:
        raise HTTPException(status_code=500, detail=f"Conflict repair audit failed: {type(error).__name__}: {error}")
