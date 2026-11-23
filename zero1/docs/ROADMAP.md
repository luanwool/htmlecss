# Roadmap funcional (o que faz sentido adicionar)

Entregue nesta rodada:
- **Pedido de alteração de escala pelo barbeiro** com aprovação do ADM. O barbeiro propõe mudança semanal (ex.: "terça só à tarde"), folga pontual ou bloqueio por período; o ADM vê a fila, os atendimentos que colidem e aprova ou recusa com justificativa. Ao aprovar, aplica automaticamente nos mesmos registros usados pelo gestor (BarberWorkSchedule, ScheduleException, BlockedInterval).
- **Programa de indicação ("Indique e ganhe")** completo: código único por cliente, link `/i/<CODIGO>`, cookie + sessão de 30 dias, bônus para o indicado no primeiro atendimento avulso concluído, crédito para o indicador, aplicação automática do crédito no próximo atendimento com teto configurável, histórico e relatórios no painel. Tudo desligável.

Próximas candidatas (ordenadas por impacto para a barbearia):

## Marketing e fidelização
1. **Programa de fidelidade** ("a cada N cortes, 1 grátis"). Cartão do cliente no painel e na área do cliente; crédito emitido automaticamente ao fechar o atendimento N.
2. **Campanha de recuperação** (clientes inativos há 60/90 dias) com e-mail automático + link único de desconto, limitado por cliente.
3. **Lembretes por WhatsApp** via API oficial (Meta Business). Quando houver orçamento aprovado — hoje é só botão manual.
4. **Vale-presente / gift card** vendido online, com código, saldo e validade; usado como o crédito de indicação.
5. **NPS pós-atendimento**: e-mail no dia seguinte com nota de 0 a 10 e comentário, agregado no painel.
6. **Lista de espera** para horários concorridos: cliente entra na fila; se alguém cancela, dispara aviso aos primeiros.
7. **Ficha do cliente** (preferências do corte, histórico de observações, anotações do barbeiro) direto na tela do atendimento.
8. **Reserva recorrente** ("todo 2º sábado, 16h"): cria a série, trata exceções sem bagunçar o histórico.
9. **Pacotes/combos** (ex.: 4 cortes + 1 barba) vendidos online como uma variação do Clube.

## Gestão do dia a dia
10. **Caixa diário** com abertura/fechamento, conferência cega e contagem por forma de pagamento.
11. **Produtos e vendas adicionais** (pomada, barba balm) com estoque, movimentação e comissão por produto.
12. **Metas e gamificação por barbeiro**: barra de evolução mensal, ranking de corte + barba, bônus por meta.
13. **Comissão por produto vendido**, separada da comissão de serviço.
14. **Despesas recorrentes** (aluguel, luz) já reconhecidas por cron, para o fluxo de caixa ficar completo.

## Operação e disponibilidade
15. **Chat interno simples** (ADM ↔ barbeiro) para avisos operacionais.
16. **"Encaixe rápido"** pelo barbeiro no celular (um toque: hora atual, serviço rápido, cliente de balcão).
17. **Mapa de calor da agenda** por hora e dia da semana, para ajudar a ajustar escalas com base em dados reais.
18. **Bloqueio automático de atendimento** após N faltas consecutivas (configurável), com aviso para o cliente.
19. **Agenda compartilhada em tela grande** no salão (modo "monitor"), mostrando os próximos horários de cada barbeiro.
20. **Múltiplas unidades** (quando a Zero 1 abrir uma segunda loja): uma barbearia por `tenant`, com relatórios comparativos.

## Dados e SEO
21. **Página pública de cada barbeiro** (`/barbeiros/nome`) com portfólio e CTA direto.
22. **Blog/notícias** para SEO local.
23. **Google Business Profile**: integrar horário, avaliações e botão "reservar" (quando estiver no ar).

É só me dizer quais entrariam na próxima rodada.
