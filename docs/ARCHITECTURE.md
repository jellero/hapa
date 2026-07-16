# Architettura tecnica HAPA

Ultimo riesame: 16 luglio 2026.

## 1. Scopo

Questo documento definisce il confine applicativo di HAPA dopo l’estrazione del runtime asincrono nel repository `jellero/hapa-automation`.

HAPA è il sistema autorevole per anagrafiche, regole commerciali e stato operativo. `hapa-automation` è il sistema autorevole per scheduling, delivery asincrona e stato tecnico dei provider.

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

HAPA non esegue scheduler o adapter in background.

## 3. Responsabilità di hapa-automation

Il servizio separato governa:

- scheduler dei job;
- consumer e publisher RabbitMQ;
- inbox idempotente;
- outbox di delivery;
- retry, backoff, lock e dead letter;
- cursori e watermark dei provider;
- proiezioni locali dei dati HAPA necessari ai worker;
- adapter Space, marketplace, GLS e BRT;
- riconciliazioni e metriche tecniche.

Il servizio usa un proprio PostgreSQL. Non accede al database HAPA.

## 4. Proprietà dei dati

| Area | Sistema autorevole |
|---|---|
| clienti e indirizzi | HAPA |
| ordini e transizioni | HAPA |
| anagrafica prodotti | HAPA |
| prezzo base e stock applicati al prodotto | HAPA, da eventi Space |
| regole di ricarico | HAPA |
| prezzo finale desiderato | HAPA |
| scheduler e job | `hapa-automation` |
| cursori provider | `hapa-automation` |
| tentativi e dead letter | `hapa-automation` |
| proiezioni tecniche per adapter | `hapa-automation` |
| versione remota restituita dal provider | provider, registrata da HAPA dopo l’esito |

Ogni dato ha un solo writer autorevole.

## 5. Messaggistica

RabbitMQ collega i due sistemi tramite eventi e comandi versionati.

Un messaggio contiene almeno:

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

Regole:

- `message_id` è globale e stabile;
- il consumer registra il messaggio prima di applicarlo;
- la deduplica avviene nel database locale del consumer;
- gli handler sono idempotenti;
- l’ordine globale non è presunto;
- gli aggiornamenti di entità usano una versione sorgente;
- almeno due versioni consecutive di schema devono essere gestibili durante il deploy;
- credenziali e segreti non transitano nei payload.

## 6. Transactional outbox HAPA

HAPA mantiene l’outbox perché ordine, prodotto o regola commerciale e relativo evento devono essere confermati o annullati insieme.

L’outbox HAPA non è uno scheduler e non esegue chiamate provider. È soltanto il buffer transazionale degli eventi destinati al confine di messaggistica.

Il relay RabbitMQ HAPA dovrà:

1. reclamare un batch con lock limitato;
2. pubblicare con publisher confirm;
3. segnare il messaggio come pubblicato;
4. riprovare soltanto errori temporanei;
5. esporre metriche tecniche senza incorporare logica provider.

## 7. Flusso catalogo

1. `hapa-automation` acquisisce da Space prodotto, prezzo e stock.
2. Pubblica un evento Space versionato.
3. HAPA aggiorna la propria anagrafica prodotto.
4. L’operatore gestisce i ricarichi dalla UI HAPA.
5. HAPA calcola e versiona il prezzo finale desiderato.
6. HAPA pubblica una richiesta di aggiornamento offerta.
7. `hapa-automation` chiama il marketplace.
8. HAPA applica l’esito ricevuto.

Lo stock Space rimane un dato del prodotto. Eventuali riserve o politiche di quantità pubblicabile sono regole commerciali separate.

## 8. Flusso ordini

1. `hapa-automation` importa l’ordine dal canale.
2. HAPA deduplica e persiste cliente, ordine e righe.
3. HAPA produce eventi di dominio nella propria outbox.
4. `hapa-automation` invia l’ordine a Space o esegue altre operazioni provider.
5. Gli esiti ritornano a HAPA tramite RabbitMQ.
6. Picking e decisioni manuali avvengono in HAPA.
7. Le richieste di spedizione vengono eseguite dal servizio asincrono.

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

`Core/Outbox` resta in HAPA come infrastruttura di persistenza degli eventi. Non contiene più scheduler o catalogo dei job.

## 10. Persistenza

PostgreSQL HAPA conserva lo stato autorevole del dominio. Sono richiesti:

- vincoli applicativi anche a livello database;
- `TIMESTAMPTZ` per gli istanti;
- importi monetari in unità minori;
- optimistic locking per gli aggregati modificabili;
- transazioni esplicite;
- idempotency key uniche;
- migrazioni versionate;
- backup e restore testati.

PostgreSQL `hapa-automation` conserva esclusivamente stato tecnico e proiezioni ricostruibili.

## 11. Redis

Redis resta una dipendenza HAPA per capacità temporanee esplicitamente definite. Non viene usato per condividere stato autorevole tra HAPA e `hapa-automation`.

## 12. Interfaccia

La UI HAPA espone:

- dashboard;
- clienti;
- ordini;
- catalogo prodotti, prezzo e stock;
- gestione ricarichi;
- picking;
- spedizioni;
- integrazioni;
- audit;
- utenti e impostazioni.

Non espone scheduler, worker, retry provider o dead letter. Tali funzioni appartengono al pannello operativo futuro di `hapa-automation`.

## 13. Deploy

I due repository producono immagini e Compose separati.

```text
HAPA stack
  nginx
  php
  postgres-hapa
  redis

hapa-automation stack
  worker
  postgres-automation
  rabbitmq
```

In ambienti reali RabbitMQ può essere gestito come servizio condiviso esterno. Le applicazioni usano credenziali e virtual host distinti.

## 14. Sicurezza

- nessun database condiviso;
- account database separati;
- credenziali RabbitMQ separate per publisher e consumer;
- TLS per connessioni non locali;
- allowlist delle routing key;
- limiti alla dimensione dei messaggi;
- payload minimizzati;
- audit delle modifiche commerciali in HAPA;
- audit tecnico dei retry in `hapa-automation`;
- rotazione dei segreti indipendente.

## 15. Stato

Implementato in HAPA:

- dominio ordine e persistenza transazionale;
- outbox transazionale;
- modello catalogo e motore ricarichi;
- UI presentazionale del catalogo;
- rimozione del runtime automazioni integrato.

Da completare in HAPA:

- repository prodotto e read model;
- CRUD autorizzato dei ricarichi;
- relay/consumer RabbitMQ;
- autenticazione, autorizzazione e CSRF;
- vertical slice reali.

Da completare in `hapa-automation`:

- runtime RabbitMQ e PostgreSQL;
- scheduler persistente;
- proiezioni HAPA;
- adapter provider;
- metriche, dead letter e riconciliazioni.
