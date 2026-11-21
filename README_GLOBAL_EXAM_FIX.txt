BCP SCHOOL-WIDE EXAM TIMETABLE FIX - LOCAL ONLY

1) BACK UP your local database bcp_scheduling and C:\xampp\htdocs\sched first.
2) Run RESET_EXAM_SCHEDULES_2026_2027_S1.sql in phpMyAdmin on the LOCAL bcp_scheduling database only after checking the SELECT preview. This deletes DEMO exam records for 2026-2027 Semester 1, including related Academic Office EXAM approval records. Regular class schedules and CLASS approvals are untouched.
3) Replace your Exam Timetable module files with the four files from this archive:
   app/modules/04-exam-timetable-generator/exam-timetable-generator.php
   app/modules/04-exam-timetable-generator/exam-global-common.php
   app/modules/04-exam-timetable-generator/exam-global-preview.php
   app/modules/04-exam-timetable-generator/exam-global-save.php
   (Other project files are included unchanged.)
4) Start XAMPP Apache/MySQL and Python FastAPI exam solver, then Ctrl+F5.
5) Choose academic period and dates, click Generate ALL Programs, review the count, then Confirm & Save ALL Programs.
6) Select each program in Program dropdown ONLY to view / print its saved exam timetable.

School-wide generation includes each active college program with an ACTIVE DEMO regular-class schedule. Each program must meet solver requirements, and any failure aborts the global preview without saving exams. Each program's schedules are checked for room/proctor collisions with previously generated programs; final save re-reads the database, revalidates all results, and writes all programs in a single database transaction.

IMPORTANT: No MySQL database was accessed or reset by ChatGPT. The supplied SQL must be run manually locally. No end-to-end runtime test was performed here because your live XAMPP database and Python solver are not available.
