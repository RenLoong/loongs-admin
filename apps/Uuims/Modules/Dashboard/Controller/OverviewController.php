<?php

declare(strict_types=1);

namespace App\Uuims\Modules\Dashboard\Controller;

use App\Uuims\Models\Admin;
use App\Uuims\Samples\SampleTenants;
use App\Uuims\Support\Api;
use App\Uuims\Support\I18n;
use Loongs\Http\Request;
use Loongs\Http\Response;
use Loongs\Orm\Orm;

/**
 * Data of the 平台概览 dashboard (page uuims.dashboard.overview, RENDER.md §6.1 / §14).
 *
 * Real data: admins (row-level data scope of the current admin applies through the Admin model),
 * roles, departments, WeChat bindings. There is no login log table, so the "最近登录" series counts
 * admins by the day of their last login. Tenant charts are SAMPLE data (SampleTenants) until the
 * tenant module (P2) exists; their routes live under /uuims/api/samples/*.
 */
final class OverviewController
{
    /** Stat cards: {admins, admins_mom, enabled, roles, depts, wechat}. */
    public function stats(Request $request): Response
    {
        $admins = $this->admins();
        $total = count($admins);
        $monthAgo = (new \DateTimeImmutable('-30 days'))->format('Y-m-d H:i:s');
        $before = count(array_filter($admins, static fn (array $a): bool => $a['created_at'] !== null && $a['created_at'] < $monthAgo));
        $ids = array_column($admins, 'id');

        return Api::ok([
            'admins' => $total,
            'admins_mom' => $before > 0 ? round(($total - $before) / $before, 4) : null,
            'enabled' => count(array_filter($admins, static fn (array $a): bool => $a['status'] === 1)),
            'roles' => (int) Orm::table('roles')->count(),
            'depts' => (int) Orm::table('depts')->count(),
            'wechat' => $ids === [] ? 0 : (int) Orm::table('admin_wechat')->whereIn('admin_id', $ids)->count(),
        ]);
    }

    /** Last 30 days: [{date, created, active}] (active = admins whose last login was that day). */
    public function adminTrend(Request $request): Response
    {
        $days = [];
        $start = new \DateTimeImmutable('today -29 days');
        for ($i = 0; $i < 30; $i++) {
            $days[$start->modify("+{$i} days")->format('Y-m-d')] = ['created' => 0, 'active' => 0];
        }
        foreach ($this->admins() as $a) {
            foreach (['created' => $a['created_at'], 'active' => $a['last_login_at']] as $k => $at) {
                $d = $at !== null ? substr($at, 0, 10) : null;
                if ($d !== null && isset($days[$d])) {
                    $days[$d][$k]++;
                }
            }
        }
        $rows = [];
        foreach ($days as $date => $v) {
            $rows[] = ['date' => $date] + $v;
        }

        return Api::ok($rows);
    }

    /** Donut: [{name, status, count}]. */
    public function adminStatus(Request $request): Response
    {
        $by = [1 => 0, 0 => 0];
        foreach ($this->admins() as $a) {
            $by[$a['status'] === 1 ? 1 : 0]++;
        }

        return Api::ok([
            ['name' => I18n::t('正常'), 'status' => 1, 'count' => $by[1]],
            ['name' => I18n::t('停用'), 'status' => 0, 'count' => $by[0]],
        ]);
    }

    /** Per department (rank + stacked bar), most admins first: [{name, count, enabled, disabled}]. */
    public function deptAdmins(Request $request): Response
    {
        $names = Orm::table('depts')->pluck('name', 'id')->all();
        $by = [];
        foreach ($this->admins() as $a) {
            $key = $a['dept_id'] !== null && isset($names[$a['dept_id']]) ? (string) $names[$a['dept_id']] : I18n::t('未分配部门');
            $by[$key] ??= ['name' => $key, 'count' => 0, 'enabled' => 0, 'disabled' => 0];
            $by[$key]['count']++;
            $by[$key][$a['status'] === 1 ? 'enabled' : 'disabled']++;
        }
        $rows = array_values($by);
        usort($rows, static fn (array $x, array $y): int => [$y['count'], $x['name']] <=> [$x['count'], $y['name']]);

        return Api::ok(array_slice($rows, 0, 10));
    }

    // ---------------------------------------------------------------- SAMPLE series (no tenant module yet)

    public function sampleTenantOpens(Request $request): Response { return Api::ok(I18n::deep(SampleTenants::tenantOpens())); }

    public function sampleHealth(Request $request): Response { return Api::ok(I18n::deep(SampleTenants::health())); }

    public function sampleTenantProfile(Request $request): Response { return Api::ok(I18n::deep(SampleTenants::tenantProfile())); }

    public function sampleTenantScale(Request $request): Response { return Api::ok(I18n::deep(SampleTenants::tenantScale())); }

    /**
     * Admins visible to the current admin (data scope), only the columns the overview needs.
     *
     * @return list<array{id: int, status: int, dept_id: ?int, created_at: ?string, last_login_at: ?string}>
     */
    private function admins(): array
    {
        $out = [];
        foreach (Admin::query()->get()->all() as $a) {
            $out[] = [
                'id' => (int) $a->id,
                'status' => (int) $a->status,
                'dept_id' => $a->dept_id !== null ? (int) $a->dept_id : null,
                'created_at' => self::dt($a->created_at),
                'last_login_at' => self::dt($a->last_login_at),
            ];
        }

        return $out;
    }

    private static function dt(mixed $v): ?string
    {
        return match (true) {
            $v instanceof \DateTimeInterface => $v->format('Y-m-d H:i:s'),
            is_string($v) && $v !== '' => $v,
            default => null,
        };
    }
}
