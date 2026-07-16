# Automazioni ordini e spedizioni

Ultimo riesame: 16 luglio 2026.

## Scopo e stato

Il piano copre due flussi coordinati: ordini e spedizioni, dal marketplace a HAPA, Space e GLS/BRT; catalogo commerciale, da prezzi e disponibilità Space ai ricarichi HAPA e alle offerte marketplace. Il passaggio legacy CSV/FTP resta censito per gli ordini finché il percorso concordato non viene sostituito.

Sono implementati e verificati:

- transactional outbox scritta nello stesso commit dell’ordine;
- messaggi con schema versione, correlation ID e idempotency key;
- claim concorrente PostgreSQL tramite `FOR UPDATE SKIP LOCKED`;
- worker identity, lock token, recupero dei lock scaduti e limite tentativi;
- retry con backoff esponenziale, jitter e supporto a un `Retry-After` tipizzato;
- stato terminale dead letter;
- registry di handler tramite servizi taggati;
- scheduler persistente con lock e recupero dopo interruzione;
- comando one-shot `php bin/console automation:run`;
- proiezione idempotente degli eventi ordine nell’audit log;
- schema e contratti per prezzi, disponibilità, scorta di sicurezza, ricarichi e offerte marketplace;
- pagina `/ui/automation` con il piano operativo completo.

Gli handler verso SellRapido/marketplace, Space, GLS e BRT non sono implementati. I job corrispondenti sono quindi creati con `enabled = false`: nessuna schermata o documento li dichiara operativi.

## Job censiti

| Codice | Automazione | Cadenza | Gate e comportamento |
|---|---|---:|---|
| `accept_complete_orders` | accetta ordini completi | 10 minuti | idempotenza per account-canale e ID ordine |
| `recover_shipping_addresses` | recupera e normalizza indirizzi | 10 minuti | indirizzo non valido porta a revisione manuale |
| `import_work_orders` | importa ordini di lavoro | 10 minuti | cursore persistente e deduplica richiesti prima dell’attivazione |
| `export_space_csv` | genera e trasferisce CSV verso Space | 10 minuti | consegna FTP riconciliabile; API Space resta evoluzione preferita |
| `sync_space_catalog` | acquisisce via API prezzi e disponibilità Space | 10 minuti | cursore confermato dopo il commit, versione sorgente e scorta di sicurezza |
| `publish_marketplace_offers` | pubblica prezzi ricalcolati e quantità vendibile | 10 minuti | una delivery per account-canale, SKU e versione HAPA |
| `manage_confirmed_partials` | prosegue i parziali confermati | 10 minuti | la scelta delle quantità resta obbligatoriamente manuale |
| `retry_temporary_errors` | recupera errori temporanei e lock scaduti | ogni esecuzione | runtime implementato; non ripete errori definitivi |

## Sequenza prevista

1. acquisizione incrementale dell’ordine dal canale;
2. verifica completezza e accettazione;
3. recupero e normalizzazione dell’indirizzo;
4. persistenza dell’anagrafica ordine e dell’intenzione outbox;
5. invio verso Space tramite il percorso concordato;
6. aggiornamento disponibilità completa o parziale;
7. conferma manuale degli eventuali parziali;
8. picking e richiesta spedizione al provider selezionato;
9. acquisizione etichetta e tracking;
10. restituzione del tracking al canale e riconciliazione.

Il flusso catalogo procede in parallelo:

1. lettura incrementale di prezzo e disponibilità dall’API Space;
2. upsert idempotente dell’articolo e della versione sorgente;
3. calcolo dello stock vendibile al netto della scorta di sicurezza;
4. selezione deterministica della regola di ricarico;
5. creazione dell’intenzione di pubblicazione;
6. invio via API al solo connettore attivo per account-canale;
7. persistenza della versione remota e riconciliazione.

I dettagli e i gate specifici sono in [`CATALOG_PRICING.md`](CATALOG_PRICING.md).

GLS e BRT (Bartolini) condividono il contratto Shipping provider-neutral. Un errore di validazione dell’indirizzo non deve generare tentativi ciechi: l’ordine entra in revisione manuale e l’operatore può usare il fallback concordato con il corriere.

La richiesta etichetta e la restituzione del tracking saranno reazioni event-driven agli stati `ready_for_carrier` e `label_available`, non polling aggiuntivi. Attraverseranno comunque la stessa outbox, con idempotency key distinta per provider e operazione.

## Esecuzione

Il comando elabora un solo batch e termina:

```bash
php bin/console automation:run --worker=hapa-worker-1 --limit=50
```

Questa forma è intenzionale: cron, Kubernetes CronJob o un altro orchestratore controllano frequenza, restart e concorrenza. Ogni istanza deve avere un’identità stabile e distinta. Il claim atomico evita che due worker elaborino contemporaneamente lo stesso messaggio.

## Regole di attivazione

Un job provider può diventare `enabled` soltanto dopo:

1. contratto e mapping payload verificati;
2. credenziali conservate come secret;
3. timeout, limiti, idempotenza e classificazione errori definiti;
4. handler registrato e compatibile con la versione del messaggio;
5. test sandbox di successo, timeout dopo invio, duplicato e riconciliazione;
6. dashboard, alert e procedura dead-letter disponibili;
7. approvazione operativa esplicita.

## Lavoro ancora aperto

- heartbeat e timeout degli handler di lunga durata;
- gestione autorizzata di inspect, replay e chiusura dead letter;
- metriche su età coda, latenza, tentativi e fallimenti;
- supervisore continuativo e graceful shutdown per un futuro worker long-running;
- cursori effettivi dei provider e rate limiting distribuito;
- test di concorrenza multi-processo e compatibilità tra release;
- repository e handler per catalogo, regole prezzo e offerte;
- adapter reali SellRapido/marketplace, Space, GLS e BRT.
