<?php

namespace App\Enums;

enum ScheduleChangeKind: string
{
    case Weekly = 'weekly';           // Nova escala semanal (substitui completa de um dia da semana)
    case Exception = 'exception';     // Folga ou horário especial em DATA específica
    case Block = 'block';             // Bloqueio por intervalo (férias, ausência)

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Alteração de escala semanal',
            self::Exception => 'Folga ou horário especial',
            self::Block => 'Bloqueio por período (férias, ausência)',
        };
    }
}
