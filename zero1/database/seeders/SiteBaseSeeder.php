<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\OpeningHour;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\SiteSetting;
use App\Support\SiteSettings;
use Illuminate\Database\Seeder;

/**
 * Somente dados públicos encontrados no briefing (endereço, telefone, horário do cadastro público).
 * Tudo o mais nasce vazio ou como rascunho não publicado. Idempotente: não sobrescreve edições.
 */
class SiteBaseSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'business.name' => 'Zero 1 Barbearia',
            'business.street' => 'Av. Cesário de Melo',
            'business.number' => '4070',
            'business.district' => 'Campo Grande',
            'business.city' => 'Rio de Janeiro',
            'business.state' => 'RJ',
            'business.postal_code' => '23050-102',
            'business.country' => 'BR',
            'business.phone' => '(21) 97349-6399',
            'business.whatsapp' => '5521973496399',
            'business.whatsapp_message' => 'Olá! Vim pelo site da Zero 1 Barbearia.',
            'business.maps_url' => 'https://maps.app.goo.gl/dZc52hFThiUxPGXg7',
            'business.hours_confirmed' => '0',
            'brand.accent_color' => '#C8A46A',
            'seo.title' => 'Zero 1 Barbearia — Campo Grande, Rio de Janeiro',
            'seo.description' => 'Barbearia em Campo Grande, Rio de Janeiro. Agende seu horário online e conheça o Clube Zero 1.',
            'seo.indexable' => '1',
        ];
        foreach ($settings as $key => $value) {
            SiteSetting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
        app(SiteSettings::class)->flush();

        // Horário do cadastro público consultado (seg–sáb, 09h–19h). Marcado como "a confirmar".
        foreach (range(1, 7) as $day) {
            OpeningHour::query()->firstOrCreate(['weekday' => $day], [
                'is_closed' => $day === 7,
                'opens_at' => $day === 7 ? null : '09:00',
                'closes_at' => $day === 7 ? null : '19:00',
            ]);
        }

        $sections = [
            'hero' => [
                'eyebrow' => 'Campo Grande · Rio de Janeiro',
                'title' => 'Corte preciso. Horário marcado.',
                'subtitle' => 'Escolha o serviço, o barbeiro e o horário em poucos toques. Conheça também o Clube Zero 1.',
                'content' => ['primary_cta' => 'Agendar horário', 'secondary_cta' => 'Conhecer planos'],
            ],
            'steps' => [
                'eyebrow' => 'Como funciona', 'title' => 'Do celular à cadeira em três passos.', 'subtitle' => null,
                'content' => [
                    'step_1_title' => 'Escolha o serviço', 'step_1_text' => 'Veja valores e durações antes de marcar.',
                    'step_2_title' => 'Escolha o profissional e o horário', 'step_2_text' => 'Um barbeiro específico ou qualquer um disponível. Só aparecem horários realmente livres.',
                    'step_3_title' => 'Confirme e salve na agenda', 'step_3_text' => 'Você recebe a confirmação por e-mail e pode adicionar ao Google Agenda ou ao calendário do celular.',
                    'primary_cta' => 'Ver horários livres',
                ],
            ],
            'services' => ['eyebrow' => 'Serviços', 'title' => 'Serviços e preços', 'subtitle' => 'Valores e durações informados pela barbearia.'],
            'plans' => ['eyebrow' => 'Clube Zero 1', 'title' => 'Assinatura mensal', 'subtitle' => 'Planos com regras claras. O benefício é liberado após a confirmação do pagamento de cada mês.'],
            'team' => ['eyebrow' => 'Profissionais', 'title' => 'Quem atende você', 'subtitle' => null],
            'gallery' => ['eyebrow' => 'O espaço', 'title' => 'Galeria', 'subtitle' => null],
            'testimonials' => ['eyebrow' => 'Depoimentos', 'title' => 'Quem já passou por aqui', 'subtitle' => null,
                'content' => ['google_cta' => 'Ver avaliações no Google']],
            'faq' => ['eyebrow' => 'Dúvidas', 'title' => 'Perguntas frequentes', 'subtitle' => null],
            'location' => ['eyebrow' => 'Localização', 'title' => 'Como chegar', 'subtitle' => null],
            'cta' => ['eyebrow' => null, 'title' => 'Seu próximo horário está a poucos toques.', 'subtitle' => null,
                'content' => ['primary_cta' => 'Agendar agora']],
        ];
        $order = 0;
        foreach ($sections as $key => $data) {
            $order += 10;
            // "steps" chegou depois: entra logo após o hero também em bancos já existentes.
            PageSection::query()->firstOrCreate(['key' => $key], $data + ['sort_order' => $key === 'steps' ? 15 : $order, 'is_visible' => true]);
        }

        // Páginas legais: modelos NÃO publicados até revisão e aprovação pela barbearia.
        $draft = "> **Rascunho — não publicado.** Este texto deve ser redigido/revisado pela Zero 1 Barbearia com apoio jurídico antes da publicação.\n\n";
        $pages = [
            'termos-de-assinatura' => ['Termos do Clube Zero 1', $draft."## Cobrança recorrente\n\n[A definir]\n\n## Cancelamento\n\nO cancelamento impede a próxima renovação. O acesso ao período já pago segue até o fim do ciclo, salvo estorno, fraude ou outra condição contratual válida. [Revisar]\n\n## Uso da franquia\n\n[A definir]\n\n## Faltas e atrasos\n\n[A definir]"],
            'privacidade' => ['Política de Privacidade (LGPD)', $draft."## Dados coletados\n\nNome, e-mail, telefone e histórico de atendimentos, para viabilizar agendamentos e assinaturas. Dados de cartão são tratados exclusivamente pelo Mercado Pago e não são armazenados por este site.\n\n## Base legal e finalidade\n\n[A definir]\n\n## Retenção\n\n[A definir]\n\n## Direitos do titular\n\nVocê pode solicitar cópia, correção ou exclusão dos seus dados pela área do cliente ou pelos contatos da barbearia.\n\n## Encarregado (DPO)\n\n[A definir]"],
            'politica-de-agendamento' => ['Política de agendamento e cancelamento', $draft."## Antecedência\n\n[A definir]\n\n## Reagendamento e cancelamento\n\n[A definir]\n\n## Atrasos e ausências (no-show)\n\n[A definir]"],
        ];
        foreach ($pages as $slug => [$title, $body]) {
            Page::query()->firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => $body, 'is_published' => false]);
        }

        // Tópicos obrigatórios do FAQ, sem resposta (não publicados) — a barbearia define as regras.
        if (Faq::query()->count() === 0) {
            $questions = [
                'Como funciona a cobrança recorrente do plano?',
                'Como cancelo minha assinatura?',
                'Posso reagendar ou cancelar um horário?',
                'Quais serviços o plano cobre?',
                'Como funciona a franquia de usos do mês?',
                'O que acontece se eu me atrasar ou faltar?',
            ];
            foreach ($questions as $i => $q) {
                Faq::create(['question' => $q, 'answer' => null, 'is_published' => false, 'sort_order' => ($i + 1) * 10]);
            }
        }
    }
}
