# Flussi di business HAPA

Ultimo riesame: 17 luglio 2026.

## Catalogo Space → HAPA → SellRapido → IBS

1. Il job Automation legge una pagina incrementale da Space.
2. L'adapter valida il payload e pubblica `space.catalog.item.observed`.
3. HAPA deduplica per `message_id` e per fornitore + ID esterno + versione sorgente.
4. Il matching usa nell'ordine ID Space già collegato, EAN univoco e SKU.
5. Un articolo mai visto crea un prodotto HAPA inattivo in `pending_review` e una distinta offerta Space.
6. Un conflitto EAN/SKU entra in revisione manuale senza creare collegamenti o offerte marketplace.
7. Un prodotto già approvato non riceve sovrascritture anagrafiche da Space; cambiano costo e disponibilità dell'offerta fornitore.
8. Osservazioni più vecchie di quella già applicata vengono conservate ma ignorate.
9. Il motore commerciale calcola quantità e prezzo desiderati soltanto per prodotti approvati.
10. HAPA salva la versione prodotto/offerta e i comandi `marketplace.product.upsert.requested` e/o `marketplace.offer.publish.requested` in outbox.
11. Automation aggrega le modifiche secondo i rate limit e chiama SellRapido sul catalogo configurato.
12. SellRapido distribuisce i dati a IBS e agli altri marketplace collegati all'account.
13. Un timeout ambiguo entra in riconciliazione, non in retry cieco.
14. HAPA applica gli esiti per singolo SKU e versione, anche quando una richiesta massiva ha risultati parziali.

Per evitare sovrascritture, il catalogo SellRapido deve essere data-entry oppure avere una policy `fields_lock` esplicita. Prima del cutover viene arrestato il writer precedente.

## Ordine SellRapido → HAPA → acquisto Space

1. Automation importa da SellRapido usando `modified`, overlap, offset e limit.
2. Il token manager usa l'access token corrente o lo rinnova con il refresh token senza ripetere l'autenticazione iniziale.
3. Automation pubblica `marketplace.order.observed` con `connector = sellrapido`, account, ID tecnico, codice ordine, marketplace/channel, righe, importi e dati personali minimizzati.
4. HAPA deduplica per account di integrazione + `head.id` e conserva separatamente il codice commerciale del marketplace.
5. HAPA crea o riconcilia l'identità cliente.
6. HAPA conserva snapshot di indirizzo, dati fiscali necessari e righe economiche. Il consumer applicativo è idempotente per account + ID provider + versione sorgente e ignora gli aggiornamenti regressivi.
7. Le anomalie di prezzo, valuta, prodotto, identità o indirizzo portano a revisione manuale.
8. HAPA risolve ogni riga sull'offerta Space usando prima il collegamento prodotto, poi SKU fornitore, SKU HAPA ed EAN; ambiguità, costo mancante o stock insufficiente creano un acquisto in `manual_review` senza chiamate remote.
9. Se account, credenziali, test connessione e configurazione Space sono operativi, HAPA crea un solo `supplier_purchase_order` automatico e salva atomicamente `space.purchase_order.submit.requested` nell'outbox.
10. Automation registra l'operazione, chiama l'API PHP Space con `Idempotency-Key`, applica retry con backoff e pubblica l'esito iniziale.
11. HAPA applica `accepted` o `rejected` all'acquisto senza modificare automaticamente lo stato della vendita.

Il cliente ha comprato da HAPA tramite il marketplace gestito da SellRapido; Space è il fornitore di HAPA. Gli ID SellRapido, marketplace, HAPA e Space rimangono distinti e correlati.

## Disponibilità e parziali

- La disponibilità Space è un'osservazione fornitore.
- La quantità vendibile è una decisione commerciale HAPA.
- La quantità pubblicata è una proiezione SellRapido/marketplace.
- La quantità acquistata è nello stato dell'acquisto.
- La quantità spedibile è nello stato di fulfilment.
- Un parziale richiede una policy esplicita e, quando previsto, conferma operatore.

Queste quantità non devono essere compresse in un solo campo o stato.

## Spedizione GLS

1. L'operatore completa picking e definisce i colli.
2. HAPA valida indirizzo, quantità, peso e dimensioni.
3. HAPA salva spedizione, colli, audit e comando in un'unica transazione.
4. Automation usa la configurazione GLS selezionata e chiama `AddParcel`.
5. HAPA riceve `shipping.shipment.opened`, tracking, progressivi collo e riferimenti label.
6. HAPA richiede separatamente la chiusura; Automation usa preferibilmente `CloseWorkDayByShipmentNumber`.
7. HAPA applica `shipping.shipment.closed` soltanto dopo conferma o riconciliazione GLS.
8. La UI offre stampa e ristampa autorizzata senza duplicare la spedizione.
9. HAPA decide il fulfilment SellRapido soltanto dopo tracking e chiusura coerenti.

BRT verrà aggiunto come adapter della stessa capacità dopo discovery, senza biforcare il dominio HAPA.

## Fulfilment HAPA → SellRapido → marketplace

1. HAPA produce `marketplace.fulfilment.publish.requested` con ID tecnico SellRapido, stato, tracking e corriere.
2. Automation valida mapping corriere e coppia tracking/corriere.
3. Automation può raggruppare più ordini in `POST /api/v2/order/status` mantenendo la correlazione per indice.
4. Una risposta vuota è successo/no-op; gli elementi di errore generano esiti separati per ordine.
5. Un timeout o una richiesta remota già pendente richiede rilettura dell'ordine prima del retry.
6. HAPA applica `marketplace.fulfilment.published` soltanto quando lo stato SellRapido osservato coincide con quello richiesto.
7. SellRapido propaga tracking e stato al marketplace downstream.

L'API non consente di rimuovere tracking, corriere o data pagamento già impostati: HAPA non modella tali operazioni come comandi supportati.

## Chiusura ordine

La chiusura non coincide con una singola risposta provider. Richiede almeno:

- vendita non cancellata;
- acquisto risolto;
- quantità finali determinate;
- spedizione GLS chiusa e tracking registrato, quando necessari;
- fulfilment SellRapido/marketplace riconciliato;
- nessuna eccezione manuale aperta;
- stato fiscale coerente quando il modulo sarà attivo.

Ogni dimensione mantiene il proprio stato; un read model sintetico può mostrare lo stato operativo complessivo.

## Configurazione provider

Endpoint, account, cataloghi, contratti, capacità e frequenze sono configurati dalla UI HAPA. Password e token sono write-only e rimangono in Automation. Una modifica non abilita automaticamente l'account: test connessione e attivazione sono azioni distinte e auditabili.

## Errori

| Caso | Trattamento |
|---|---|
| messaggio duplicato | inbox, nessuna doppia applicazione |
| evento fuori ordine | confronto versione, nessuna regressione |
| timeout provider ambiguo | riconciliazione prima del retry |
| token SellRapido in scadenza | rinnovo/rotazione con lock e margine di sicurezza |
| rate limit SellRapido | backoff dal relativo header, nessun retry immediato |
| ban o credenziale invalida | sospensione account e intervento operatore |
| errore parziale prodotti/ordini | esito per indice e riconciliazione della singola entità |
| dato cliente incompleto | revisione manuale |
| prezzo o valuta incoerenti | blocco ordine/offerta e audit |
| disponibilità parziale | policy e decisione esplicita |
| label non recuperabile | spedizione esistente, retry del recupero senza ricrearla |
| fulfilment rifiutato | ordine non chiuso, riconciliazione SellRapido/canale |
