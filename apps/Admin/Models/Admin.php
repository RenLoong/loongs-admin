<?php

declare(strict_types=1);

namespace App\Admin\Models;

use App\Admin\Models\Concerns\HasDataScope;
use App\Admin\Models\Concerns\HasFieldPermissions;
use Loongs\Orm\Model\Model;

/** Platform administrator (admin_platform.admins). */
final class Admin extends Model
{
    use HasDataScope;
    use HasFieldPermissions;

    protected string $table = 'admins';
    protected string $fieldResource = 'admin';
    protected array $fillable = ['username', 'password', 'nickname', 'mobile', 'email', 'dept_id', 'status', 'is_super',
        'last_login_at', 'last_login_ip', 'password_changed_at', 'created_by'];
    protected array $hidden = ['password'];
    protected array $casts = ['id' => 'int', 'dept_id' => 'int', 'status' => 'int', 'is_super' => 'bool', 'created_by' => 'int'];

    /** Admins: "self" data scope = the admin's own record. */
    public function dataScopeColumns(): array
    {
        return ['dept' => 'dept_id', 'owner' => 'id'];
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_ARGON2ID);
    }
}
