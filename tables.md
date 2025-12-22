# Database Tables Specification (tables.md)

This document includes **all database tables** for the E‑School Platform, including the updated `lessons` + `lesson_media` structure.

---

# 1. users
```
id BIGINT PK
user_name STRING UNIQUE
password STRING
phone_number STRING
role ENUM['Manager','Teacher','Student']
email STRING NULLABLE
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- User hasOne Student
- User hasOne Teacher
- User hasOne Manager

---

# 2. students
```
id BIGINT PK
user_id FK → users.id
date_of_birth DATE
full_name STRING
country STRING
state STRING
city STRING
gender ENUM['Male','Female']
level_id FK → levels.id
class_id FK → classes.id
certificate_path STRING
personal_image_path STRING
guardian_name STRING
guardian_relationship STRING
student_phone_number STRING NULLABLE
guardian_phone_number STRING
guardian_email STRING NULLABLE
guardian_address STRING
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- Student belongsTo User
- Student belongsTo Level
- Student belongsTo Class
- Student hasMany Attendance
- Student hasMany Payments
- Student hasMany Marks

---

# 3. teachers
```
id BIGINT PK
user_id FK → users.id
date_of_birth DATE
full_name STRING
certificate_path STRING
cv_path STRING
country STRING
state STRING
city STRING
gender ENUM['Male','Female']
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- Teacher belongsTo User
- Teacher hasMany Specialization
- Teacher hasMany TeacherTimeTable
- Teacher hasMany TrackTeachers

---

# 4. managers
```
id BIGINT PK
user_id FK → users.id
date_of_birth DATE
full_name STRING
gender ENUM['Male','Female']
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- Manager belongsTo User

---

# 5. levels
```
id BIGINT PK
name STRING
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- Level hasMany Classes
- Level hasMany Subjects
- Level hasMany Lessons
- Level hasMany PapersWork
- Level hasMany ClassActivity
- Level hasMany MonthlyTests
- Level hasMany TeacherTimeTable
- Level hasMany TrackTeachers
- Level hasMany Students
- Level hasMany Marks
- Level hasMany Attendance

---

# 6. classes
```
id BIGINT PK
name STRING
level_id FK → levels.id
number_of_subjects INTEGER
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- Class belongsTo Level
- Class hasMany Students
- Class hasMany Subjects
- Class hasMany Lessons
- Class hasMany PapersWork
- Class hasMany ClassActivity
- Class hasMany MonthlyTests
- Class hasMany TeacherTimeTable
- Class hasMany TrackTeachers
- Class hasMany Marks
- Class hasMany Attendance

---

# 7. subjects
```
id BIGINT PK
name STRING
level_id FK → levels.id
class_id FK → classes.id
total_lessons INTEGER
total_degree INTEGER
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- Subject belongsTo Level
- Subject belongsTo Class
- Subject hasMany Lessons
- Subject hasMany MonthlyTests
- Subject hasMany PapersWork
- Subject hasMany Marks
- Subject hasMany Attendance
- Subject hasMany TeacherTimeTable
- Subject hasMany TrackTeachers
- Subject hasMany Specialization

---

# 8. specializations
```
id BIGINT PK
teacher_id FK → teachers.id
level_id FK → levels.id
class_id FK → classes.id
subject_id FK → subjects.id
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- Specialization belongsTo Teacher
- Specialization belongsTo Level
- Specialization belongsTo Class
- Specialization belongsTo Subject

---

# 9. lessons
```
id BIGINT PK
title STRING
summary TEXT
level_id FK → levels.id
class_id FK → classes.id
subject_id FK → subjects.id
primary_media_id FK → lesson_media.id NULLABLE
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- Lesson hasMany LessonMedia
- Lesson belongsTo Subject
- Lesson belongsTo Level
- Lesson belongsTo Class
- Lesson hasOne Quiz

---

# 10. lesson_media
```
id BIGINT PK
lesson_id FK → lessons.id

provider ENUM['cloudflare','external','none'] DEFAULT 'cloudflare'
media_type ENUM['live','vod','uploaded']

source_url STRING NULLABLE

cf_live_input_id STRING NULLABLE
cf_live_playback_id STRING NULLABLE
live_status ENUM['not_started','scheduled','live','ended'] NULLABLE
live_scheduled_at DATETIME NULLABLE
live_started_at DATETIME NULLABLE
live_ended_at DATETIME NULLABLE

cf_vod_video_id STRING NULLABLE
cf_vod_playback_id STRING NULLABLE
thumbnail_url STRING NULLABLE
duration_seconds INTEGER NULLABLE

is_active BOOLEAN DEFAULT TRUE

created_at TIMESTAMP
updated_at TIMESTAMP
```

---

# 11. quizzes
```
id BIGINT PK
lesson_id FK → lessons.id
quiz_url STRING
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- Quiz belongsTo Lesson

---

# 12. monthly_tests
```
id BIGINT PK
subject_id FK → subjects.id
level_id FK → levels.id
class_id FK → classes.id
test_url STRING
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- MonthlyTest belongsTo Subject
- MonthlyTest belongsTo Level
- MonthlyTest belongsTo Class

---

# 13. papers_work
```
id BIGINT PK
paper_path STRING
level_id FK → levels.id
class_id FK → classes.id
subject_id FK → subjects.id
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- PapersWork belongsTo Level
- PapersWork belongsTo Class
- PapersWork belongsTo Subject

---

# 14. attendance
```
id BIGINT PK
student_id FK → students.id
subject_id FK → subjects.id
level_id FK → levels.id
class_id FK → classes.id
attendance_count INTEGER
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- Attendance belongsTo Student
- Attendance belongsTo Subject
- Attendance belongsTo Class
- Attendance belongsTo Level

---

# 15. teacher_time_table
```
id BIGINT PK
day STRING
start_time TIME
end_time TIME
level_id FK → levels.id
class_id FK → classes.id
subject_id FK → subjects.id
teacher_id FK → teachers.id
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- TeacherTimeTable belongsTo Teacher
- TeacherTimeTable belongsTo Level
- TeacherTimeTable belongsTo Class
- TeacherTimeTable belongsTo Subject

---

# 16. track_teachers
```
id BIGINT PK
lesson_id FK → lessons.id
teacher_id FK → teachers.id
level_id FK → levels.id
class_id FK → classes.id
subject_id FK → subjects.id
day STRING
start_time TIME
end_time TIME
attend BOOLEAN
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- TrackTeacher belongsTo Teacher
- TrackTeacher belongsTo Lesson
- TrackTeacher belongsTo Level
- TrackTeacher belongsTo Class
- TrackTeacher belongsTo Subject

---

# 17. fees
```
id BIGINT PK
class_id FK → classes.id
total_fee DOUBLE
minimum_fee DOUBLE
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- Fee belongsTo Class
- Class hasOne Fee

---

# 18. payments
```
id BIGINT PK
student_id FK → students.id
payment_method ENUM['visa','cash']
amount DOUBLE
transaction_uid STRING UNIQUE
level_id FK → levels.id NULLABLE
class_id FK → classes.id NULLABLE
guardian_name STRING
guardian_phone_number STRING
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- Payment belongsTo Student
- Payment belongsTo Level
- Payment belongsTo Class

---

# 19. class_activities
```
id BIGINT PK
activity_name STRING
image_path STRING NULLABLE
video_path STRING NULLABLE
level_id FK → levels.id
class_id FK → classes.id
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- ClassActivity belongsTo Level
- ClassActivity belongsTo Class

---

# 20. student_guidance
```
id BIGINT PK
guidance TEXT
image_path STRING NULLABLE
video_path STRING NULLABLE
level_id FK → levels.id NULLABLE
class_id FK → classes.id NULLABLE
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- StudentGuidance belongsTo Level (optional)
- StudentGuidance belongsTo Class (optional)

---

# 21. marks
```
id BIGINT PK
student_id FK → students.id
subject_id FK → subjects.id
level_id FK → levels.id
class_id FK → classes.id
degree INTEGER
total_degree INTEGER
created_at TIMESTAMP
updated_at TIMESTAMP
```

Relationships:
- Mark belongsTo Student
- Mark belongsTo Subject
- Mark belongsTo Class
- Mark belongsTo Level
