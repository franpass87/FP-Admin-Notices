# FP Admin Notices

Un plugin WordPress che centralizza le notice del back-end in un pannello dedicato, apribile dall'admin bar senza ricaricare la pagina.

## Funzionalità

- Raccoglie automaticamente tutte le notice (`success`, `warning`, `error`, `info`) mostrate nel back-end corrente.
- Nasconde le notice dall'intestazione delle pagine di amministrazione e le ripropone in un pannello sempre disponibile.
- Pannello accessibile dall'admin bar con conteggio delle notice non lette.
- Apertura/chiusura dinamica senza ricaricare la pagina, con osservazione in tempo reale di nuove notice tramite `MutationObserver`.
- Interfaccia responsive con overlay e gestione focus per migliorare l'accessibilità.

## Installazione

1. Copia la cartella del plugin nella directory `wp-content/plugins/` del tuo sito WordPress.
2. Accedi alla dashboard di WordPress e attiva **FP Admin Notices** dalla sezione **Plugin**.

## Utilizzo

- Dopo l'attivazione troverai un'icona a forma di megafono nell'admin bar (in alto a destra).
- Il badge rosso mostra il numero di notice disponibili. Cliccando l'icona si apre il pannello con l'elenco delle notice.
- Puoi chiudere il pannello cliccando l'overlay, il pulsante di chiusura o premendo `Esc`.

## Personalizzazione

Il plugin espone i file CSS e JS in `assets/`. Puoi sovrascrivere gli stili nel tuo tema o child theme se necessario.

## Requisiti

- WordPress 5.8 o superiore.
- PHP 7.2 o superiore.
