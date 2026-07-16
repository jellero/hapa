# Corrieri e spedizioni

Questo documento definisce il confine comune delle integrazioni di spedizione e lo stato reale di GLS e BRT (Bartolini) in HAPA.

## Stato sintetico

| Capacità | Stato | Note |
|---|---|---|
| contratto `Shipping` | implementato | DTO, codici corriere e porta comune tipizzati |
| modulo `Gls` | parziale | contratto provider presente; adapter HTTP e configurazione assenti |
| modulo `Brt` | parziale | contratto provider presente; discovery, adapter HTTP e configurazione assenti |
| persistenza provider | implementata | `shipments.provider` accetta soltanto `GLS` e `BRT` |
| colli e peso tariffabile | pianificata | il DTO corrente espone ancora dati aggregati |
| creazione spedizione, label e tracking | pianificata | nessuna chiamata reale è attiva |

La presenza di BRT nell’interfaccia e nei contratti non dichiara operativa l’integrazione. Endpoint, credenziali, servizi, payload e vincoli specifici verranno implementati soltanto dopo una discovery verificata sulla documentazione e su un account di prova autorizzato.

## Ownership

| Modulo | Possiede | Non possiede |
|---|---|---|
| `Shipping` | modello normalizzato, `CarrierCode`, `CarrierAdapter`, richiesta e risultato comuni | payload HTTP, credenziali e regole specifiche del provider |
| `Gls` | mapping e adapter GLS | dominio ordine, persistenza generica delle spedizioni |
| `Brt` | mapping e adapter BRT | dominio ordine, persistenza generica delle spedizioni |
| `Orders` | decisione di predisporre un ordine alla spedizione | dettagli delle API dei corrieri |

Le dipendenze sono dichiarate in `config/module-dependencies.php`: `Gls` e `Brt` dipendono dal solo contratto pubblico di `Shipping`. Il Core non dipende da alcun modulo.

## Contratto comune

Ogni adapter espone:

```php
carrier(): CarrierCode
createShipment(ShipmentRequest $shipment, string $idempotencyKey): ShipmentResult
fetchLabel(string $labelReference): string
```

Il codice canonico persistito è `GLS` oppure `BRT`. “BRT (Bartolini)” è la dicitura leggibile usata nell’interfaccia.

I DTO comuni contengono soltanto dati normalizzati. Opzioni specifiche, dettagli HTTP e risposte grezze restano nel modulo del provider e vengono tradotti attraverso un anti-corruption layer.

## Flusso applicativo previsto

```text
caso d’uso Shipping
  -> transazione: spedizione + audit + messaggio outbox
  -> commit
worker
  -> CarrierAdapter selezionato dal CarrierCode
  -> adapter GLS oppure BRT
  -> persistenza delivery e risultato normalizzato
  -> riconciliazione
```

Controller e route non chiamano direttamente i provider. Il caso d’uso applicativo coordina dominio e transazione; l’effetto esterno viene eseguito dopo il commit tramite outbox.

## Idempotenza e persistenza

Chiave proposta:

```text
carrier:<carrier-code>:create-shipment:<shipment-id>:<shipment-version>
```

Lo schema protegge l’unicità di tracking e identificativo esterno per provider. Un timeout dopo l’invio non autorizza una creazione cieca: il worker deve prima recuperare il risultato tramite idempotency key o riconciliazione supportata dal corriere.

## Discovery obbligatoria per ogni provider

Prima di implementare GLS o BRT devono essere verificati e documentati:

1. documentazione ufficiale, versione e ambiente di prova;
2. autenticazione, rotazione e segregazione delle credenziali;
3. creazione, annullamento, ristampa, tracking e riconciliazione disponibili;
4. servizi, opzioni, contrassegno e requisiti di indirizzo;
5. formati label, content type, dimensioni massime e retention;
6. timeout, quote, rate limit e codici di errore;
7. supporto reale dell’idempotenza e strategia dopo timeout ambiguo;
8. trattamento dei dati personali, logging consentito e minimizzazione;
9. webhook, firma, anti-replay e ordinamento degli eventi, se presenti;
10. contatti operativi ed escalation in caso di indisponibilità.

## Failure mode

| Evento | Comportamento richiesto |
|---|---|
| timeout o errore temporaneo | retry con backoff, jitter e budget complessivo |
| rate limit | rispetto di `Retry-After` o finestra documentata |
| autenticazione rifiutata | blocco del provider, alert, nessun retry aggressivo |
| payload non valido | errore definitivo o revisione manuale con dettaglio redatto |
| esito ambiguo dopo invio | ricerca per idempotency key o riconciliazione prima del retry |
| label non valida | rifiuto per content type/dimensione, audit e revisione |
| provider indisponibile a lungo | coda osservabile, dead letter e procedura operativa |

Segreti, label, indirizzi e payload completi non devono comparire nei log. L’accesso alle etichette sarà autorizzato, auditato e soggetto a retention.

## Gate di completamento

Un corriere diventa “operativo” soltanto quando dispone di configurazione tipizzata, client con policy di rete, adapter reale, fake deterministico, suite di conformità condivisa, outbox/worker, persistenza delle delivery, riconciliazione, autorizzazione, audit, metriche, runbook e test end-to-end. Fino ad allora l’interfaccia deve mostrarlo come contratto pronto o parziale.
