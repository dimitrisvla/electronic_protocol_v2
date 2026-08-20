<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateApplicationSettingsRequest;
use App\Models\ApplicationSetting;
use App\Services\ApplicationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Display and update the Administrator-only application settings page.
 */
class ApplicationSettingController extends Controller
{
    /**
     * Display the currently effective typed settings.
     */
    public function index(ApplicationSettings $settings): View
    {
        Gate::authorize('viewAny', ApplicationSetting::class);

        return view('admin.settings.index', [
            'organizationName' => $settings->organizationName(),
            'activeProtocolYear' => $settings->activeProtocolYear(),
            'startingProtocolNumber' =>
                $settings->startingProtocolNumber(),
            'automaticProtocolNumbering' =>
                $settings->usesAutomaticProtocolNumbering(),
        ]);
    }

    /**
     * Persist a complete, validated settings form atomically.
     */
    public function update(
        UpdateApplicationSettingsRequest $request,
        ApplicationSettings $settings
    ): RedirectResponse {
        Gate::authorize('updateAny', ApplicationSetting::class);

        $validated = $request->validated();

        DB::transaction(function () use ($request, $settings, $validated) {
            $settings->setOrganizationName(
                $validated['organization_name']
            );
            $settings->setActiveProtocolYear(
                $validated['active_protocol_year'] === null
                    ? null
                    : (int) $validated['active_protocol_year']
            );
            $settings->setStartingProtocolNumber(
                (int) $validated['starting_protocol_number']
            );
            $settings->setAutomaticProtocolNumbering(
                $request->boolean('automatic_protocol_numbering')
            );
        });

        return redirect()
            ->route('admin.settings.index')
            ->with('success', __('flash.settings.updated'));
    }
}
