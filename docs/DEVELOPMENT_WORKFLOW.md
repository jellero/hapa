# Sviluppare una funzionalità HAPA

Questa guida adatta al progetto HAPA i principi di ownership, separazione dei livelli e completezza funzionale. Descrive il pattern richiesto per il codice nuovo e distingue sempre ciò che esiste da ciò che è ancora in roadmap.

## Prima del codice

Per ogni funzionalità dichiarare:

| Decisione | Domanda |
|---|---|
| dato | quale modulo ne è proprietario? |
| caso d’uso | quale modulo coordina l’azione? |
| attore | chi può eseguirla e con quale permesso? |
| scope | account, canale, cliente, ordine o provider coinvolto? |
| transazione | quali scritture devono riuscire o fallire insieme? |
| effetto esterno | serve outbox, retry o riconciliazione? |
| dati sensibili | cosa va minimizzato, redatto e sottoposto a retention? |
| stato | implementato, parziale o pianificato? |

In HAPA non esiste il tenant `hotel_id` dei documenti di riferimento. L’ownership va modellata sul confine reale: account-canale per marketplace, cliente/ordine per le anagrafiche e provider per le integrazioni. Non introdurre un tenant artificiale.

## Percorso canonico

```text
HTTP request
  -> routing e metadati di sicurezza
  -> controller sottile
  -> application service nominato come caso d’uso
  -> validator puro / policy di dominio
  -> repository
  -> PostgreSQL
  -> risposta finalizzata
```

Per un effetto esterno:

```text
application service
  -> transazione: dominio + audit + outbox
  -> commit
worker
  -> contratto pubblico del provider
  -> adapter HTTP concreto
  -> delivery, retry e riconciliazione
```

La foundation HAPA corrente offre bootstrap, container DI compilato, configurazioni tipizzate, routing, risposta HTTP, health check, repository ordine, transaction manager, outbox, worker/scheduler one-shot e UI presentazionale. Autenticazione, permessi, CSRF, repository cliente/read model e adapter provider reali sono ancora gate di roadmap: il nuovo codice non deve descriverli come già operativi.

## Responsabilità dei livelli

### Controller

- traduce request, contesto e risultato in HTTP;
- riceve dipendenze tipizzate;
- non contiene SQL, transazioni o orchestrazioni multi-step;
- non chiama direttamente adapter esterni;
- non importa repository o servizi interni di altri moduli.

### Application service

- rappresenta un caso d’uso riconoscibile;
- riceve contesto e attore come argomenti espliciti;
- normalizza, valida e coordina policy e repository;
- definisce il transaction boundary;
- registra audit e outbox quando necessari;
- non legge request/sessione e non restituisce `Response`.

### Validator e policy

- il validator puro non interroga il database;
- la policy applica regole di dominio più restrittive;
- unicità e ownership che richiedono persistenza vengono coordinate dal service;
- una policy non sostituisce autenticazione e permessi della route.

### Repository

- usa SQL parametrizzato e operazioni atomiche;
- applica lo scope di ownership definito dal caso d’uso;
- non legge sessione, request o permessi;
- non costruisce risposte HTTP e non scrive audit autonomamente.

## Core e moduli

La direzione normale è `Module -> Core`. Il Core non importa classi dei moduli.

Una dipendenza tra moduli è ammessa soltanto attraverso un contratto pubblico minimo, deve essere presente in `config/module-dependencies.php` e non può creare cicli. `composer architecture:check` verifica registrazione, direzione, contratti e cicli.

Esempio corrente:

```text
Gls -> Shipping\Contract
Brt -> Shipping\Contract
```

Il modulo `Shipping` possiede il modello comune; i moduli provider possiedono mapping e adapter concreti.

## Sequenza di una vertical slice

1. definire ownership, invarianti e failure mode;
2. aggiungere migrazione, vincoli e indici;
3. introdurre tipi di dominio e contratti pubblici;
4. implementare validator, policy e caso d’uso;
5. implementare repository e transazione;
6. registrare audit e outbox nello stesso commit quando richiesto;
7. aggiungere delivery HTTP/API protetta da autenticazione, permesso e CSRF per le mutazioni;
8. collegare UI senza dati dimostrativi ingannevoli;
9. coprire casi positivi, negativi, ownership, rollback, integrazione e architettura;
10. aggiornare roadmap, stato e ADR se cambia una decisione strutturale.

## Integrazioni esterne

Non dedurre i payload da conoscenza generica. Prima di un adapter reale servono specifiche ufficiali e account di prova. Documentare sempre:

- autenticazione e gestione segreti;
- timeout di connessione, inattività e durata totale;
- retry ammessi e budget complessivo;
- idempotenza e comportamento dopo esito ambiguo;
- rate limit e quote;
- ordine degli eventi e anti-replay per webhook;
- dati personali, logging, retention e redazione;
- indisponibilità prolungata, dead letter e riconciliazione;
- ownership operativa ed escalation.

## Stato e Definition of Done

- **implementato**: comportamento collegato e verificato nel percorso reale;
- **parziale**: base o contratto presenti, ma mancano gate essenziali;
- **pianificato**: nessun comportamento operativo disponibile.

Una pagina che si apre o un’interfaccia PHP non rende completa una funzione. La Definition of Done include persistenza, sicurezza, autorizzazione, transazione, audit/outbox, gestione errori, test, documentazione, migrazione/deploy e rollback o strategia forward-only dichiarata.

Usare [`PR_CHECKLIST.md`](PR_CHECKLIST.md) prima di pubblicare modifiche applicative.
