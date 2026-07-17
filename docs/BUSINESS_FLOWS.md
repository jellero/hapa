# Flussi di business HAPA

Ultimo riesame: 17 luglio 2026.

## Catalogo Space → offerta IBS

1. Il job Automation legge una pagina incrementale da Space.
2. L’adapter valida il payload e pubblica `space.catalog.item.observed`.
3. HAPA deduplica e applica soltanto una versione più recente.
4. HAPA aggiorna prodotto e offerta fornitore nella stessa transazione.
5. Il motore commerciale calcola quantità e prezzo desiderati per l’account IBS.
6. HAPA salva la versione dell’offerta e `marketplace.offer.publish.requested` in outbox.
7. Automation chiama IBS usando l’idempotency key del comando.
8. Un timeout ambiguo entra in riconciliazione, non in retry cieco.
9. HAPA applica `marketplace.offer.published` o `marketplace.offer.failed`.

Temu e Amazon riutilizzano la stessa vertical slice dopo discovery e test, senza aggiungere condizioni provider al dominio di pricing.

## Ordine IBS → acquisto Space

1. Automation importa da IBS a partire dal watermark confermato.
2. Pubblica `marketplace.order.observed` con account, ID ordine, righe, importi e dati personali minimizzati.
3. HAPA crea o riconcilia l’identità cliente.
4. HAPA conserva snapshot di indirizzo e righe economiche.
5. L’unicità account + ordine esterno impedisce duplicati.
6. Le anomalie di prezzo, valuta, prodotto o indirizzo portano a revisione manuale.
7. HAPA crea un `supplier_purchase_order` distinto dalla vendita.
8. HAPA invia `space.purchase_order.submit.requested`.
9. Automation chiama Space e registra la singola operazione provider.
10. HAPA applica accettazione, rifiuto o disponibilità parziale all’acquisto.

Il cliente ha comprato da HAPA; Space è il fornitore di HAPA. Il riferimento Space non sostituisce il numero ordine HAPA o IBS.

## Disponibilità e parziali

- La disponibilità Space è un’osservazione fornitore.
- La quantità vendibile è una decisione commerciale HAPA.
- La quantità acquistata è nello stato dell’acquisto.
- La quantità spedibile è nello stato di fulfillment.
- Un parziale richiede una policy esplicita e, quando previsto, conferma operatore.

Queste quantità non devono essere compresse in un solo campo o stato.

## Spedizione GLS

1. L’operatore completa picking e definisce i colli.
2. HAPA valida indirizzo, quantità, peso e dimensioni.
3. HAPA salva spedizione, colli, audit e comando in un’unica transazione.
4. Automation chiama GLS con idempotenza o riconciliazione.
5. HAPA riceve tracking e riferimento etichetta.
6. La UI offre stampa e ristampa autorizzata senza duplicare la spedizione.
7. HAPA decide la chiusura operativa e richiede il fulfilment IBS.

BRT verrà aggiunto come adapter della stessa capacità dopo discovery, senza biforcare il dominio HAPA.

## Chiusura ordine

La chiusura non coincide con una singola risposta provider. Richiede almeno:

- vendita non cancellata;
- acquisto risolto;
- quantità finali determinate;
- spedizione e tracking registrati, quando necessari;
- fulfilment marketplace riconciliato;
- nessuna eccezione manuale aperta;
- stato fiscale coerente quando il modulo sarà attivo.

Ogni dimensione mantiene il proprio stato; un read model sintetico può mostrare lo stato operativo complessivo.

## Errori

| Caso | Trattamento |
|---|---|
| messaggio duplicato | inbox, nessuna doppia applicazione |
| evento fuori ordine | confronto versione, nessuna regressione |
| timeout provider ambiguo | riconciliazione prima del retry |
| dato cliente incompleto | revisione manuale |
| prezzo o valuta incoerenti | blocco ordine/offerta e audit |
| disponibilità parziale | policy e decisione esplicita |
| label non recuperabile | spedizione esistente, retry del recupero senza ricrearla |
| fulfilment rifiutato | ordine non chiuso, riconciliazione canale |
