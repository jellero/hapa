# Automazioni esterne

Ultimo riesame: 16 luglio 2026.

Il runtime delle automazioni non appartiene più al processo HAPA.

Scheduler, worker, retry provider, dead letter, cursori, inbox, outbox di delivery, proiezioni operative e adapter asincroni sono gestiti nel repository separato [`jellero/hapa-automation`](https://github.com/jellero/hapa-automation).

## Confine applicativo

HAPA conserva:

- dati autorevoli di clienti, ordini e prodotti;
- prezzo e stock sincronizzati da Space;
- regole di ricarico gestite dall’interfaccia;
- stato desiderato delle offerte marketplace;
- transactional outbox degli eventi generati dai propri casi d’uso.

`hapa-automation` conserva:

- database PostgreSQL autonomo;
- scheduler e configurazione dei job;
- inbox idempotente dei messaggi RabbitMQ;
- outbox dei messaggi da pubblicare;
- retry, backoff, dead letter e lock dei worker;
- cursori e watermark dei provider;
- proiezioni locali necessarie agli adapter;
- esecuzione delle chiamate verso Space, marketplace, GLS e BRT.

Nessun servizio legge o scrive direttamente il database dell’altro.

## Sincronizzazione RabbitMQ

RabbitMQ trasporta eventi e comandi; non replica direttamente PostgreSQL.

Flusso previsto:

1. HAPA salva una modifica di dominio e il relativo evento nella stessa transazione.
2. L’evento viene pubblicato su RabbitMQ con un identificativo stabile e una versione di schema.
3. `hapa-automation` registra il messaggio nella propria inbox e aggiorna una proiezione locale.
4. Il worker esegue l’operazione esterna idempotente.
5. L’esito viene scritto nell’outbox di `hapa-automation` e pubblicato su RabbitMQ.
6. HAPA consuma l’esito e aggiorna il proprio stato autorevole.

Ogni messaggio deve contenere almeno:

- `message_id`;
- `event_type`;
- `schema_version`;
- `occurred_at`;
- `correlation_id`;
- `causation_id` quando disponibile;
- payload tipizzato.

## Flussi trasferiti

Il servizio dedicato eseguirà:

- importazione ordini dai marketplace;
- accettazione ordini sul canale sorgente;
- recupero e normalizzazione degli indirizzi esterni;
- invio ordini a Space;
- acquisizione da Space di anagrafica prodotto, prezzo e stock;
- pubblicazione delle offerte marketplace calcolate da HAPA;
- creazione spedizioni ed etichette GLS/BRT;
- restituzione di tracking e fulfilment;
- retry, riconciliazioni e gestione dead letter.

Le decisioni manuali, le anagrafiche e le regole commerciali restano in HAPA.

## Vincoli

- un solo writer attivo per account, canale, capacità e SKU;
- consumer idempotenti con deduplica per `message_id`;
- ordinamento non presunto tra code diverse;
- compatibilità tra almeno due versioni consecutive di messaggio;
- payload senza credenziali o dati personali non necessari;
- retry soltanto per errori temporanei;
- errori definitivi sottoposti a revisione autorizzata;
- metriche e alert nel servizio `hapa-automation`.

Il codice e la documentazione operativa dei job sono mantenuti nel repository dedicato. Questo file resta come riferimento del confine tra i due sistemi.
