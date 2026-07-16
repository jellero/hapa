# Corrieri e spedizioni

Ultimo riesame: 16 luglio 2026.

Questo documento definisce il confine applicativo HAPA per spedizioni, GLS e BRT. L’esecuzione degli adapter corriere appartiene al repository autonomo `jellero/hapa-automation`.

## Stato sintetico

| Capacità | Repository | Stato |
|---|---|---|
| contratto normalizzato Shipping | HAPA | implementato come DTO e porta applicativa |
| decisione di predisporre la spedizione | HAPA | pianificata |
| persistenza applicativa di colli, tracking e stato | HAPA | parziale |
| adapter GLS | `hapa-automation` | non implementato |
| adapter BRT | `hapa-automation` | non implementato |
| creazione label e riconciliazione provider | `hapa-automation` | non implementata |
| applicazione dell’esito in HAPA | HAPA | non implementata |

La presenza di GLS e BRT nei contratti o nell’interfaccia non dichiara operativa alcuna integrazione.

## Ownership

HAPA possiede:

- modello normalizzato della spedizione;
- `CarrierCode`;
- dati autorevoli di colli, quantità, tracking e stato applicativo;
- decisioni manuali e autorizzazioni;
- richiesta transazionale di creazione spedizione;
- applicazione dell’esito ricevuto tramite RabbitMQ;
- audit delle azioni operative.

`hapa-automation` possiede:

- credenziali e configurazione tecnica dei corrieri;
- mapping dei payload GLS e BRT;
- client HTTP o altri protocolli provider;
- idempotenza della chiamata esterna;
- retry, rate limit e dead letter;
- recupero label e riconciliazione;
- metriche tecniche del provider.

Nessun adapter GLS o BRT viene eseguito nel processo HAPA e nessun servizio legge il database dell’altro.

## Contratto applicativo

Il contratto HAPA descrive dati normalizzati, senza dettagli HTTP:

```php
carrier(): CarrierCode
createShipment(ShipmentRequest $shipment, string $idempotencyKey): ShipmentResult
fetchLabel(string $labelReference): string
```

Questa porta rappresenta la capacità applicativa e i DTO condivisi. L’implementazione asincrona concreta deve risiedere in `hapa-automation` oppure essere sostituita da contratti RabbitMQ equivalenti e versionati.

Il codice canonico persistito è `GLS` oppure `BRT`. “BRT (Bartolini)” resta la dicitura leggibile usata nell’interfaccia.

## Flusso previsto

```text
operatore o caso d’uso HAPA
  -> transazione: spedizione + audit + outbox
  -> RabbitMQ
  -> inbox hapa-automation
  -> adapter GLS o BRT
  -> outbox hapa-automation
  -> RabbitMQ
  -> consumer HAPA
  -> tracking, label reference e stato applicativo
```

Controller e route non chiamano direttamente i provider. HAPA non persiste tentativi tecnici o segreti corriere; conserva soltanto lo stato applicativo necessario al dominio e all’operatore.

## Idempotenza

Chiave logica proposta:

```text
carrier:<carrier-code>:create-shipment:<shipment-id>:<shipment-version>
```

Il comando RabbitMQ deve avere anche un `message_id` stabile. Un timeout dopo l’invio non autorizza una seconda creazione cieca: `hapa-automation` deve recuperare il risultato tramite idempotency key o riconciliazione supportata dal corriere.

## Contratti di messaggio da definire

Prima dell’implementazione reale devono essere congelati almeno:

- `shipping.create.requested`;
- `shipping.created`;
- `shipping.label.created`;
- `shipping.failed`;
- `shipping.reconciliation.requested`;
- `shipping.reconciled`.

Ogni payload deve includere soltanto i dati necessari, una versione schema, correlation ID e identificativi applicativi stabili. Indirizzi, contatti e label richiedono minimizzazione e retention esplicite.

## Discovery obbligatoria

Per GLS e BRT devono essere verificati:

1. documentazione ufficiale, versione e ambiente di prova;
2. autenticazione, rotazione e segregazione delle credenziali;
3. creazione, annullamento, ristampa, tracking e riconciliazione disponibili;
4. servizi, opzioni, contrassegno e requisiti di indirizzo;
5. formati label, content type, dimensioni massime e retention;
6. timeout, quote, rate limit e codici di errore;
7. supporto reale dell’idempotenza e strategia dopo timeout ambiguo;
8. trattamento dei dati personali e logging consentito;
9. webhook, firma, anti-replay e ordinamento degli eventi;
10. contatti operativi ed escalation.

## Failure mode

| Evento | Responsabilità |
|---|---|
| timeout o errore temporaneo | retry limitato in `hapa-automation` |
| rate limit | rispetto della finestra provider |
| autenticazione rifiutata | blocco adapter e alert tecnico |
| payload HAPA non valido | rifiuto definitivo senza chiamata provider |
| esito ambiguo | riconciliazione prima del retry |
| label non valida | rifiuto per tipo/dimensione e evento di errore redatto |
| provider indisponibile a lungo | coda osservabile e dead letter nel servizio esterno |
| evento di esito duplicato | consumer HAPA idempotente |

## Gate di completamento

Un corriere diventa operativo soltanto quando:

- il contratto RabbitMQ è coperto da test producer/consumer in entrambi i repository;
- l’adapter reale e un fake deterministico esistono in `hapa-automation`;
- idempotenza, timeout, retry e riconciliazione sono verificati;
- HAPA applica gli esiti in una transazione idempotente;
- autenticazione, autorizzazione e audit HAPA sono attivi;
- metriche, alert, retention label e runbook sono disponibili;
- un test end-to-end attraversa HAPA, RabbitMQ, adapter sandbox e ritorno dell’esito.
