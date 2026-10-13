<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Request;
use App\Services\FeatureService;

final class PlanController extends Controller
{
    public function index(): void
    {
        $this->view('platform/plans/index', [
            'title' => 'Plans',
            'breadcrumbs' => [
                ['label' => 'Platform', 'url' => 'platform'],
                ['label' => 'Plans'],
            ],
            'plans' => FeatureService::plans(),
            'names' => FeatureService::planNames(),
            'catalog' => FeatureService::catalog(),
            'usage' => FeatureService::usageCounts(),
            'limits' => FeatureService::workspaceLimits(),
        ]);
    }

    public function update(): void
    {
        $codes = Request::input('plan_codes', []);
        $names = Request::input('plan_names', []);
        $features = Request::input('plan_features', []);
        $limits = Request::input('plan_limits', []);
        if (!is_array($codes) || !is_array($names) || !is_array($features) || !is_array($limits)) {
            flash('danger', 'The plan form could not be read.');
            $this->redirect('platform/plans');
        }

        $known = FeatureService::plans();
        $rows = [];
        $errors = [];
        foreach ($codes as $code) {
            $code = (string) $code;
            if (!isset($known[$code])) {
                continue;
            }
            $name = trim((string) ($names[$code] ?? ''));
            if ($name === '' || strlen($name) > 80) {
                $errors['name_' . $code] = ['Enter a name of 80 characters or fewer for ' . $code . '.'];
                continue;
            }
            $picked = isset($features[$code]) && is_array($features[$code]) ? $features[$code] : [];
            $rows[] = ['code' => $code, 'name' => $name, 'features' => $picked, 'limit' => $limits[$code] ?? ''];
        }

        if ($errors !== [] || count($rows) !== count($known)) {
            if ($errors === []) {
                $errors['plan_codes'] = ['Refresh the page and save again.'];
            }
            $this->withErrors($errors);
            $this->redirect('platform/plans');
        }

        FeatureService::save($rows);
        flash('success', 'Plans saved. Companies keep the features already saved on their own form.');
        $this->redirect('platform/plans');
    }

    public function store(): void
    {
        $message = FeatureService::add(
            (string) Request::input('code', ''),
            (string) Request::input('name', ''),
            (string) Request::input('copy_from', 'FREE'),
            Request::input('workspace_limit', null)
        );
        if ($message !== null) {
            $this->withErrors(['code' => [$message]]);
            $this->redirect('platform/plans');
        }

        flash('success', 'Plan added. Check the features you want, then save.');
        $this->redirect('platform/plans');
    }

    public function destroy(): void
    {
        $message = FeatureService::remove((string) Request::input('code', ''));
        if ($message !== null) {
            flash('danger', $message);
            $this->redirect('platform/plans');
        }

        flash('success', 'Plan removed.');
        $this->redirect('platform/plans');
    }
}
