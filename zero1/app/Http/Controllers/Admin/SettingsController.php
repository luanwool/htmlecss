<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\OpeningHour;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaUploader;
use App\Support\BusinessTime;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(SiteSettings $settings): View
    {
        return view('admin.settings.edit', [
            'settings' => $settings,
            'hours' => OpeningHour::orderBy('weekday')->get(),
            'logo' => ($id = $settings->get('brand.logo_media_id')) ? MediaAsset::find($id) : null,
            'ogImage' => ($id = $settings->get('seo.og_media_id')) ? MediaAsset::find($id) : null,
        ]);
    }

    public function update(Request $request, SiteSettings $settings, MediaUploader $uploader, AuditLogger $audit): RedirectResponse
    {
        $str = ['nullable', 'string', 'max:255'];
        $v = $request->validate([
            's.business_name' => ['required', 'string', 'max:120'],
            's.business_legal_name' => $str,
            's.business_document' => ['nullable', 'string', 'max:20'],
            's.business_street' => $str,
            's.business_number' => ['nullable', 'string', 'max:20'],
            's.business_district' => $str,
            's.business_city' => $str,
            's.business_state' => ['nullable', 'string', 'size:2'],
            's.business_postal_code' => ['nullable', 'regex:/^\d{5}-?\d{3}$/'],
            's.business_phone' => ['nullable', 'string', 'max:20'],
            's.business_whatsapp' => ['nullable', 'regex:/^\d{12,13}$/'],
            's.business_whatsapp_message' => $str,
            's.business_email' => ['nullable', 'email', 'max:255'],
            's.business_instagram' => ['nullable', 'url', 'max:255', 'starts_with:https://'],
            's.business_maps_url' => ['nullable', 'url', 'max:255', 'starts_with:https://'],
            's.business_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            's.business_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            's.business_hours_note' => $str,
            's.brand_accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            's.brand_tagline' => $str,
            's.seo_title' => ['nullable', 'string', 'max:70'],
            's.seo_description' => ['nullable', 'string', 'max:170'],
            's.marketing_ga4_id' => ['nullable', 'regex:/^G-[A-Z0-9]{4,12}$/'],
            's.marketing_meta_pixel_id' => ['nullable', 'regex:/^\d{8,20}$/'],
            's.booking_slot_interval' => ['nullable', 'integer', 'in:5,10,15,20,30'],
            's.booking_min_advance_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            's.booking_max_advance_days' => ['nullable', 'integer', 'min:1', 'max:180'],
            's.booking_cancel_min_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
            's.booking_assignment_policy' => ['nullable', 'in:balance,first'],
            's.booking_max_open_per_customer' => ['nullable', 'integer', 'min:1', 'max:20'],
            's.booking_unpaid_policy' => ['nullable', 'in:flag,cancel'],
            's.booking_reminder_hours' => ['nullable', 'regex:/^\d{1,3}(,\d{1,3})*$/'],
            's.booking_walk_in_past_hours' => ['nullable', 'integer', 'min:0', 'max:72'],
            's.whatsapp_driver' => ['nullable', 'in:manual,cloud'],
            's.whatsapp_auto_send_on_booking' => ['nullable', 'boolean'],
            's.whatsapp_cloud_phone_id' => ['nullable', 'string', 'max:40'],
            's.whatsapp_cloud_token' => ['nullable', 'string', 'max:512'],
            'hours' => ['required', 'array'],
            'hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'date_format:H:i'],
            'logo' => ['nullable', 'image', 'mimes:png,webp,jpg,jpeg', 'max:2048'],
            'og_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=1200,min_height=630'],
        ], [
            's.business_whatsapp.regex' => 'WhatsApp: somente números com DDI e DDD, ex.: 5521973496399.',
            's.marketing_ga4_id.regex' => 'ID do GA4 no formato G-XXXXXXX.',
        ]);

        $values = [];
        foreach ($v['s'] as $field => $value) {
            $key = preg_replace('/_/', '.', $field, 1); // business_name -> business.name
            abort_unless(array_key_exists($key, SiteSettings::SCHEMA), 422);
            $values[$key] = $value;
        }
        $values['business.hours_confirmed'] = $request->boolean('business_hours_confirmed');
        $values['seo.indexable'] = $request->boolean('seo_indexable');
        $values['booking.late_cancel_forfeits'] = $request->boolean('booking_late_cancel_forfeits');
        $values['booking.no_show_consumes_credit'] = $request->boolean('booking_no_show_consumes_credit');
        $values['mp.enabled'] = $request->boolean('mp_enabled');

        $before = $settings->all();

        DB::transaction(function () use ($request, $v, $values, $settings, $uploader) {
            foreach (range(1, 7) as $day) {
                $row = $v['hours'][$day] ?? [];
                $closed = ! empty($request->input("hours.{$day}.is_closed"));
                if (! $closed && (empty($row['opens_at']) || empty($row['closes_at']) || $row['opens_at'] >= $row['closes_at'])) {
                    throw ValidationException::withMessages(["hours.{$day}.opens_at" => 'Horário inválido para '.BusinessTime::weekdayName($day).'.']);
                }
                OpeningHour::updateOrCreate(['weekday' => $day], [
                    'is_closed' => $closed,
                    'opens_at' => $closed ? null : $row['opens_at'],
                    'closes_at' => $closed ? null : $row['closes_at'],
                ]);
            }

            foreach (['logo' => ['brand.logo_media_id', 'logo'], 'og_image' => ['seo.og_media_id', 'og']] as $input => [$key, $collection]) {
                if ($request->hasFile($input)) {
                    $old = ($id = $settings->get($key)) ? MediaAsset::find($id) : null;
                    $media = $uploader->store($request->file($input), $collection, ['alt_text' => $values['business.name']]);
                    $values[$key] = $media->id;
                    if ($old) {
                        $uploader->delete($old);
                    }
                }
            }

            $settings->set($values);
        });

        $changed = array_filter($values, fn ($val, $k) => (string) ($before[$k] ?? '') !== (string) (is_bool($val) ? (int) $val : $val), ARRAY_FILTER_USE_BOTH);
        if ($changed) {
            $audit->log('settings.updated', null, array_intersect_key($before, $changed), $changed);
        }

        return back()->with('status', 'Configurações salvas.');
    }
}
