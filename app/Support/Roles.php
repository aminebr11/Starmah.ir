<?php

namespace App\Support;

/**
 * نقش‌های پلتفرم (با Spatie Permission مدیریت می‌شوند).
 */
class Roles
{
    public const SUPER_ADMIN  = 'super_admin';  // مالک پلتفرم
    public const SCHOOL_ADMIN = 'school_admin'; // مدیر مدرسه
    public const TEACHER      = 'teacher';      // معلم
    public const STUDENT      = 'student';      // دانش‌آموز
    public const PARENT       = 'parent';       // والد

    public const ALL = [
        self::SUPER_ADMIN,
        self::SCHOOL_ADMIN,
        self::TEACHER,
        self::STUDENT,
        self::PARENT,
    ];
}
