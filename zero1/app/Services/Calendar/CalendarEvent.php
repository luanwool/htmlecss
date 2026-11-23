<?php

namespace App\Services\Calendar;

use App\Models\Appointment;
use App\Support\SiteSettings;

/**
 * Convites de calendário. O UID é estável por agendamento; cada reagendamento/cancelamento incrementa
 * SEQUENCE, para que Apple Calendar/Outlook atualizem ou removam o evento já importado.
 */
class CalendarEvent
{
    public function __construct(private SiteSettings $settings) {}

    public function ics(Appointment $a): string
    {
        $cancelled = $a->status->isCancelled();
        $location = trim($this->settings->businessName().', '.$this->settings->fullAddress(), ', ');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Zero 1 Barbearia//Agenda//PT-BR',
            'CALSCALE:GREGORIAN',
            'METHOD:'.($cancelled ? 'CANCEL' : 'PUBLISH'),
            'BEGIN:VEVENT',
            'UID:'.$a->uid.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'SEQUENCE:'.$a->ics_sequence,
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$a->starts_at->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$a->ends_at->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($this->title($a)),
            'DESCRIPTION:'.$this->escape($this->description($a)),
            'LOCATION:'.$this->escape($location),
            'STATUS:'.($cancelled ? 'CANCELLED' : 'CONFIRMED'),
        ];
        if (! $cancelled) {
            $lines = array_merge($lines, ['BEGIN:VALARM', 'TRIGGER:-PT2H', 'ACTION:DISPLAY', 'DESCRIPTION:'.$this->escape($this->title($a)), 'END:VALARM']);
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([$this, 'fold'], $lines))."\r\n";
    }

    /** Link "Adicionar ao Google Agenda" (o cliente confirma; não há sincronização automática). */
    public function googleUrl(Appointment $a): string
    {
        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $this->title($a),
            'dates' => $a->starts_at->copy()->utc()->format('Ymd\THis\Z').'/'.$a->ends_at->copy()->utc()->format('Ymd\THis\Z'),
            'details' => $this->description($a),
            'location' => trim($this->settings->businessName().', '.$this->settings->fullAddress(), ', '),
            'ctz' => config('zero1.timezone'),
        ]);
    }

    public function title(Appointment $a): string
    {
        return $a->service_name_snapshot.' — '.$this->settings->businessName();
    }

    private function description(Appointment $a): string
    {
        $parts = ['Profissional: '.$a->barber->display_name, 'Serviço: '.$a->service_name_snapshot.' ('.$a->duration_minutes_snapshot.' min)'];
        if ($dir = $this->settings->directionsUrl()) {
            $parts[] = 'Como chegar: '.$dir;
        }
        $parts[] = 'Gerenciar: '.route('account.appointments.show', $a->uid);

        return implode("\n", $parts);
    }

    private function escape(string $v): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $v);
    }

    /** Dobra linhas em 75 octetos (RFC 5545). */
    private function fold(string $line): string
    {
        $out = '';
        while (strlen($line) > 75) {
            $cut = 75;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--; // não quebra caracteres UTF-8
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
        }

        return $out.$line;
    }
}
