# Architettura tecnica HAPA

Ultimo riesame: 16 luglio 2026.

## 1. Scopo

Questo documento descrive esclusivamente l’architettura del repository HAPA e il suo contratto di integrazione con sistemi esterni.

Il runtime asincrono non fa parte di questo progetto. Codice, architettura interna, database, job, configurazione e deploy delle automazioni sono mantenuti nel repository autonomo [`jellero/hapa-automation`](https://github.com/jellero/hapa-automation).

## 2. Responsabilità di HAPA

HAPA governa:

- clienti, identità esterne e indirizzi;
- ordini, righe e transizioni di stato;
- anagrafica prodotti;
- prezzo e stock sincronizzati da Space;
- regole di ricarico configurate tramite interfaccia;
- prezzo finale desiderato e stato delle offerte;
- picking, colli, spedizioni e tracking;
- utenti, autorizzazioni e audit;
- transactional outbox degli eventi applicativi.

HAPA non contiene scheduler, worker provider, cursori di polling, retry provider, dead letter o adapter asincroni.

## 3. Dipendenza esterna hapa-automation

HAPA comunica con `hapa-automation` esclusivamente tramite RabbitMQ e contratti di messaggio versionati.

Dal punto di vista di HAPA, `hapa-automation` è un servizio esterno che:

- acquisisce dati dai provider;
- esegue comandi asincroni;
- restituisce eventi di esito;
- mantiene autonomamente il proprio stato tecnico.

HAPA non importa codice dal repository esterno, non include il suo Compose e non accede al suo database. La documentazione operativa del servizio è in [`hapa-automation/docs/RUNTIME_ARCHITECTURE.md`](https://github.com/jellero/hapa-automation/blob/main/docs/RUNTIME_ARCHITECTURE.md); i contratti correnti sono in [`docs/MESSAGE_CONTRACTS.md`](https://github.com/jellero/hapa-automation/blob/main/docs/MESSAGE_CONTRACTS.md).

La foundation disponibile sulla `main` di `hapa-automation` comprende:

- stack Docker autonomo;
- PostgreSQL dedicato;
- topologia RabbitMQ;
- envelope versionati;
- inbox idempotente;
- outbox con claim, retry e dead letter;
- scheduler persistente;
- proiezioni prodotto, ricarico e ordine;
- worker long-running con graceful shutdown.

Questa disponibilità non rende operativi i flussi provider. Gli adapter reali Space, marketplace, GLS e BRT, il relay/consumer RabbitMQ lato HAPA, i test di contratto congiunti e l’osservabilità completa restano da implementare. I job provider sono disabilitati per impostazione predefinita.

## 4. Proprietà dei dati

| Area | Sistema autorevole |
|---|---|
| clienti e indirizzi | HAPA |
| ordini e transizioni | HAPA |
| anagrafica prodotti | HAPA |
| prezzo base e stock applicati al prodotto | HAPA, da eventi Space |
| regole di ricarico | HAPA |
| prezzo finale desiderato | HAPA |
| stato applicativo delle offerte | HAPA |
| stato tecnico dei job e dei provider | `hapa-automation` |
| versione remota restituita dal provider | provider, registrata da HAPA dopo l’esito |

Ogni dato ha un solo writer autorevole. Nessun servizio legge o scrive direttamente il database dell’altro.

## 5. Messaggistica

RabbitMQ trasporta eventi e comandi; non replica direttamente PostgreSQL.

Ogni messaggio contiene almeno:

```json
{
  "message_id": "uuid",
  "event_type": "catalog.product.updated",
  "schema_version": 1,
  "occurred_at": "2026-07-16T13:34:00Z",
  "correlation_id": "uuid",
  "causation_id": "uuid-or-null",
  "payload": {}
}
```

Regole HAPA:

- `message_id` è globale e stabile;
- i consumer deduplicano nel database HAPA;
- l’applicazione di un messaggio e l’aggiornamento della inbox avvengono nello stesso transaction boundary;
- gli aggiornamenti di entità usano una versione sorgente;
- l’ordine globale tra code non è presunto;
- almeno due versioni consecutive di schema devono essere compatibili durante il deploy;
- credenziali e segreti non transitano nei payload;
- dati personali e payload vengono minimizzati.

## 6. Transactional outbox HAPA

HAPA mantiene la propria transactional outbox perché la modifica di dominio e la produzione dell’evento devono essere confermate o annullate insieme.

L’outbox HAPA:

- persiste eventi dei casi d’uso HAPA;
- usa idempotency key e correlation ID;
- non pianifica job;
- non esegue chiamate provider;
- non contiene logica di retry provider.

Il relay RabbitMQ HAPA dovrà reclamare messaggi, pubblicare con publisher confirm e registrare l’esito della sola consegna al broker.

## 7. Flusso catalogo

1. `hapa-automation` acquisisce da Space prodotto, prezzo e stock.
2. Pubblica un evento versionato su RabbitMQ.
3. HAPA deduplica il messaggio e aggiorna la propria anagrafica prodotto.
4. L’operatore gestisce i ricarichi dalla UI HAPA.
5. HAPA calcola e versiona il prezzo finale desiderato.
6. HAPA produce un comando o evento di pubblicazione offerta.
7. `hapa-automation` esegue la chiamata marketplace.
8. HAPA applica l’esito ricevuto.

Lo stock Space rimane un dato del prodotto. Eventuali politiche di quantità pubblicabile sono regole commerciali separate.

## 8. Flusso ordini e spedizioni

1. `hapa-automation` importa l’ordine dal canale.
2. HAPA deduplica e persiste cliente, ordine, righe e snapshot degli indirizzi.
3. HAPA produce gli eventi applicativi nella propria outbox.
4. Il servizio esterno esegue accettazione, invio a Space o altre operazioni provider.
5. Gli esiti ritornano a HAPA tramite RabbitMQ.
6. Picking e decisioni manuali avvengono in HAPA.
7. HAPA produce la richiesta di spedizione.
8. Il servizio esterno crea label, tracking e fulfilment.
9. HAPA applica e mostra gli esiti.

## 9. Moduli HAPA

```text
app/
  Composition/
  Core/
    Clock/
    Configuration/
    Database/
    Health/
    Http/
    Logging/
    Outbox/
    Ui/
    View/
  Modules/
    Catalog/
    Customers/
    Orders/
    Marketplace/
    Space/
    Shipping/
    Gls/
    Brt/
```

`Core/Outbox` contiene esclusivamente la persistenza transazionale degli eventi HAPA. Non contiene scheduler o worker delle automazioni.

I contratti provider presenti nei moduli HAPA descrivono DTO e capacità applicative; l’esecuzione asincrona degli adapter appartiene al repository esterno.

## 10. Persistenza

PostgreSQL HAPA conserva lo stato autorevole del dominio. Sono richiesti:

- vincoli applicativi anche a livello database;
- `TIMESTAMPTZ` per gli istanti;
- importi monetari in unità minori;
- optimistic locking per gli aggregati modificabili;
- transazioni esplicite;
- idempotency key uniche;
- inbox e outbox locali;
- migrazioni versionate;
- backup e restore testati.

Redis resta una dipendenza HAPA per capacità temporanee esplicitamente definite. Non viene usato per condividere stato autorevole con altri servizi.

## 11. Interfaccia

La UI HAPA espone:

- dashboard;
- clienti;
- ordini;
- anagrafica prodotti, prezzo e stock;
- gestione ricarichi;
- picking;
- spedizioni;
- configurazione delle integrazioni;
- audit;
- utenti e impostazioni.

Non espone scheduler, worker, retry provider o dead letter. La route `/ui/automation` non appartiene a HAPA.

## 12. Deploy

Il Compose HAPA contiene esclusivamente i servizi del progetto:

```text
nginx
php
postgres-hapa
redis
```

`hapa-automation` viene costruito e distribuito dal proprio repository. I due progetti hanno pipeline, immagini, migrazioni, database e cicli di rilascio separati. La sua foundation è già presente su `main`, ma i job provider restano disabilitati finché non sono disponibili adapter e contratti end-to-end verificati.

Per modifiche coordinate:

1. i contratti devono essere compatibili con la versione già distribuita;
2. vengono aperte PR distinte nei due repository;
3. l’ordine di deploy viene dichiarato;
4. i job provider restano disabilitati finché entrambi i lati non sono verificati.

## 13. Sicurezza

- nessun database condiviso;
- account database separati;
- credenziali RabbitMQ separate per publisher e consumer;
- TLS per connessioni non locali;
- allowlist delle routing key;
- limiti alla dimensione dei messaggi;
- payload minimizzati;
- audit delle modifiche commerciali in HAPA;
- rotazione dei segreti indipendente;
- nessuna credenziale del servizio esterno nel repository HAPA.

## 14. Stato HAPA

Implementato:

- dominio ordine e persistenza transazionale;
- transactional outbox;
- modello catalogo e motore ricarichi;
- UI presentazionale del catalogo;
- rimozione del runtime automazioni da codice, CLI, route, schema e Compose HAPA;
- decisione architetturale per repository e database separati.

Da completare in HAPA:

- repository prodotto e read model;
- CRUD autorizzato dei ricarichi;
- relay e consumer RabbitMQ;
- inbox idempotente;
- autenticazione, autorizzazione e CSRF;
- vertical slice applicative reali;
- test di contratto coordinati con `hapa-automation`.
