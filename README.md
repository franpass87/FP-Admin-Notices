# FP Admin Notices

Un plugin WordPress che centralizza le notice del back-end in un pannello dedicato, apribile dall'admin bar senza ricaricare la pagina.

## Funzionalità

- Raccoglie automaticamente tutte le notice (`success`, `warning`, `error`, `info`) mostrate nel back-end corrente.
- Nasconde le notice dall'intestazione delle pagine di amministrazione e le ripropone in un pannello sempre disponibile.
- Pannello accessibile dall'admin bar con conteggio delle notice non lette e annuncio vocale delle nuove comunicazioni.
- Possibilità di segnare le singole notice come lette/non lette con persistenza per utente tramite REST API.
- Azioni rapide per segnare tutte le notice visibili come lette/non lette e mostrare/nascondere quelle archiviate.
- Filtri per severità, campo di ricerca e scorciatoia da tastiera (`Alt` + `Shift` + `N`) per un accesso rapido.
- Apertura/chiusura dinamica senza ricaricare la pagina, con osservazione in tempo reale di nuove notice tramite `MutationObserver` e apertura automatica per gli errori critici (opzionale).
- Interfaccia responsive con overlay, focus trap e controlli tastiera per migliorare l'accessibilità.

## Installazione

1. Copia la cartella del plugin nella directory `wp-content/plugins/` del tuo sito WordPress.
2. Accedi alla dashboard di WordPress e attiva **FP Admin Notices** dalla sezione **Plugin**.

## Utilizzo

- Dopo l'attivazione troverai un'icona a forma di megafono nell'admin bar (in alto a destra).
- Il badge rosso mostra il numero di notice non lette. Cliccando l'icona si apre il pannello con l'elenco delle notice.
- Puoi filtrare per severità, cercare testualmente e segnare le singole notice come lette/non lette oppure mostrarle nuovamente nella pagina.
- Dal gruppo di **Azioni rapide** puoi archiviare o ripristinare tutte le notice filtrate e scegliere se visualizzare anche quelle già archiviate.
- Il pannello può essere chiuso cliccando l'overlay, il pulsante di chiusura o premendo `Esc`. Premi `Alt` + `Shift` + `N` per aprirlo/chiuderlo rapidamente.

### Impostazioni

- Dal menu **Impostazioni → Pannello notifiche** puoi scegliere quali ruoli possano vedere il pannello, includere o meno gli avvisi di aggiornamento (`update nag`), decidere se aprire automaticamente il pannello quando arriva un errore critico e limitare il caricamento alle schermate amministrative che preferisci.

## Personalizzazione

Il plugin espone i file CSS e JS in `assets/`. Puoi sovrascrivere gli stili nel tuo tema o child theme se necessario.

### Hook disponibili

- `fp_admin_notices_allowed_screens`: modifica dinamicamente l'elenco di screen ID su cui caricare il pannello.
- `fp_admin_notices_should_render`: forza l'attivazione o l'esclusione del pannello per una determinata schermata (`WP_Screen`).
- Evento JS `fpAdminNotices:listUpdated`: emesso ogni volta che l'elenco viene renderizzato, con dettagli su filtri e notice visibili.

## Requisiti

- WordPress 5.8 o superiore.
- PHP 7.2 o superiore.
