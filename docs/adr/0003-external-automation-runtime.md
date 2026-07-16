# ADR 0003 — Runtime automazioni esterno

- Stato: accettata
- Data: 16 luglio 2026

## Contesto

HAPA conteneva scheduler, worker outbox, retry e pagina operativa delle automazioni nello stesso runtime che governa anagrafiche, prodotti e ordini. Questo accoppiava il ciclo applicativo alle integrazioni provider e rendeva più difficile scalare, distribuire e manutenere separatamente il lavoro asincrono.

HAPA deve invece conservare l’anagrafica prodotti con prezzo e stock sincronizzati da Space e permettere la gestione dei ricarichi tramite interfaccia.

## Decisione

Il runtime asincrono viene trasferito nel repository `jellero/hapa-automation`.

I due sistemi usano:

- immagini Docker separate;
- Compose e cicli di deploy separati;
- database PostgreSQL separati;
- RabbitMQ per eventi e comandi versionati;
- inbox e outbox locali per idempotenza e affidabilità;
- nessun accesso diretto al database dell’altro servizio.

HAPA mantiene la transactional outbox perché la modifica di dominio e la produzione dell’evento devono essere atomiche. La delivery, i retry provider e le riconciliazioni appartengono a `hapa-automation`.

## Conseguenze positive

- ownership dei dati più chiara;
- isolamento dei guasti provider;
- scalabilità indipendente dei worker;
- deploy indipendenti;
- database tecnico delle automazioni ricostruibile dalle proiezioni;
- UI HAPA focalizzata su dati e decisioni commerciali.

## Costi e rischi

- consistenza eventuale tra i servizi;
- necessità di contratti di messaggio versionati;
- gestione di duplicati e messaggi fuori ordine;
- maggiore complessità operativa di RabbitMQ;
- necessità di osservabilità distribuita e correlation ID end-to-end.

## Alternative rifiutate

### Database condiviso

Rifiutato perché introduce accoppiamento di schema, writer concorrenti e deploy coordinati.

### Replica diretta del database tramite RabbitMQ

Non applicabile: RabbitMQ trasporta messaggi, non replica PostgreSQL. La sincronizzazione avviene tramite eventi e proiezioni idempotenti.

### Worker nello stesso container HAPA

Rifiutato perché mantiene accoppiati ciclo HTTP, deploy e consumo di risorse delle integrazioni.

## Regole di implementazione

- ogni entità ha un solo writer autorevole;
- ogni messaggio ha `message_id`, `event_type`, `schema_version`, `occurred_at` e `correlation_id`;
- il consumer deduplica nel proprio database;
- gli aggiornamenti usano versioni sorgente;
- i retry sono ammessi solo per errori temporanei;
- due release consecutive devono poter convivere durante il deploy;
- le modifiche coordinate usano PR distinte nei due repository.
