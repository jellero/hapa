# Strategia integrazioni marketplace

Ultimo riesame: 16 luglio 2026.

## 1. Scopo e stato

Questo documento definisce il portafoglio delle integrazioni marketplace future e i vincoli comuni di implementazione. Non dichiara adapter operativi: ogni integrazione resta **pianificata** finché non supera i gate tecnici, di sicurezza e di collaudo descritti di seguito.

La distinzione fondamentale è:

- **canale di vendita**: il marketplace sul quale nasce l’ordine;
- **connettore**: il percorso tecnico usato da HAPA per comunicare con il canale;
- **account venditore**: la singola configurazione autorizzata, con credenziali, perimetro e policy propri.

SellRapido è quindi modellato come connettore aggregatore, non come canale di vendita. Amazon, eMAG, Temu e IBS sono canali. Per uno stesso account e canale può essere attivo un solo percorso per ciascuna capacità: import ordini e pubblicazione offerte non devono avere due writer concorrenti.

## 2. Portafoglio pianificato

| Elemento | Ruolo in HAPA | Percorso futuro | Stato |
|---|---|---|---|
| SellRapido | connettore aggregatore | ordini e offerte tramite adapter HAPA → SellRapido, soltanto per capacità confermate | pianificato |
| Amazon | canale di vendita | ordini e offerte tramite adapter diretto; eventuale aggregatore solo dopo conferma contrattuale | pianificato |
| eMAG | canale di vendita | ordini e offerte tramite adapter diretto; eventuale aggregatore solo dopo conferma contrattuale | pianificato |
| Temu | canale di vendita | ordini e offerte tramite percorso partner formalmente supportato | pianificato |
| IBS | canale di vendita | percorso iniziale tramite SellRapido; adapter diretto solo con specifiche ufficiali del partner | pianificato |

La scelta tra adapter diretto e SellRapido viene presa per singolo account dopo una discovery verificabile. Non vengono mantenuti due import concorrenti sullo stesso account e canale.

## 3. Identità canonica

I codici applicativi iniziali sono:

```text
channel:   amazon | emag | temu | ibs
connector: sellrapido | amazon | emag | temu
```

L’identità esterna di un ordine comprende almeno:

```text
account configurato + canale + external_order_id
```

Il connettore descrive il percorso di trasporto e viene conservato nelle delivery tecniche e nell’audit. Non sostituisce il canale: un ordine Amazon ricevuto tramite SellRapido resta un ordine del canale Amazon.

Prima del primo adapter reale, ogni record `marketplaces` deve rappresentare una sola coppia account-canale. Più record possono usare lo stesso `adapter_key`, per esempio `sellrapido`, senza perdere l’identità del canale sorgente. La stessa identità delimita una proiezione prezzo/stock in `marketplace_offers`.

## 4. Contratto comune

Ogni adapter marketplace deve dichiarare:

- il connettore utilizzato;
- i canali supportati dalla specifica istanza configurata;
- l’account venditore al quale è vincolato;
- capacità disponibili e limitazioni;
- strategia di import incrementale, paginazione e cursore;
- regole di accettazione, annullamento, fulfilment e tracking;
- capacità di aggiornare prezzo e quantità insieme o separatamente;
- identificativo offerta, versione remota e tempi di propagazione;
- idempotency key e strategia di riconciliazione;
- classificazione degli errori e budget complessivo dei retry;
- trattamento dei dati personali e tempi di conservazione.

Le differenze tra provider non entrano nel dominio. Payload, autenticazione, firma, paginazione e codici di stato rimangono nell’adapter e vengono tradotti in DTO applicativi tipizzati.

Il contratto ordini resta `MarketplaceAdapter`; la capacità commerciale usa `MarketplaceOfferAdapter` con `MarketplaceOfferUpdate` e `MarketplaceOfferPublication`. Prezzo finale e quantità vendibile arrivano dal modulo Catalog: il provider non sovrascrive prezzo base Space, scorta di sicurezza o regola HAPA. Vedere [`CATALOG_PRICING.md`](CATALOG_PRICING.md).

## 5. Gate di discovery per ogni integrazione

Prima di sviluppare un adapter devono essere disponibili e versionati:

1. account sandbox o ambiente di test, quando offerto;
2. documentazione tecnica ufficiale o contratto del partner;
3. autorizzazioni richieste e procedura di rotazione/revoca;
4. operazioni realmente abilitate per l’account;
5. limiti di quota, paginazione e finestre temporali;
6. semantica degli stati ordine e delle quantità parziali;
7. accesso all’indirizzo di spedizione e vincoli sui dati personali;
8. modalità di invio tracking e fulfilment;
9. operazioni prezzo/stock, atomicità, granularità, valuta e arrotondamento;
10. comportamento dopo timeout, duplicati e risposte ambigue;
11. webhook disponibili, firma, anti-replay e procedura di riconciliazione.

Le capacità non confermate restano disabilitate. Un adapter non simula funzionalità che il provider o l’account non garantiscono.

## 6. Sequenza di implementazione

Per ogni connettore selezionato:

1. acquisire e congelare la versione delle specifiche;
2. produrre una matrice operazione × capacità × errore;
3. completare DTO, errori tipizzati e configurazione account;
4. implementare un fake adapter e una suite di conformità condivisa;
5. implementare client HTTP, autenticazione, timeout, quote e redazione;
6. coprire import, accettazione, indirizzo, tracking, pubblicazione offerte e riconciliazione;
7. verificare idempotenza e recovery con PostgreSQL e outbox reali;
8. eseguire un pilot su un solo account e canale;
9. abilitare gradualmente il traffico con metriche e possibilità di arresto rapido.

L’ordine di realizzazione tra SellRapido, Amazon, eMAG, Temu e IBS viene deciso solo dopo la discovery, in base ad accesso tecnico, copertura del flusso HAPA e rischio operativo.

## 7. Prevenzione di import e pubblicazioni concorrenti

Il passaggio da SellRapido a un adapter diretto, o viceversa, richiede una procedura esplicita:

1. sospensione del vecchio import;
2. salvataggio del watermark finale;
3. riconciliazione degli ordini ancora aperti;
4. associazione degli identificativi già conosciuti;
5. attivazione del nuovo connettore da un watermark concordato;
6. verifica dei duplicati prima dell’elaborazione;
7. conservazione dell’audit della migrazione.

L’invio di tracking e fulfilment continua sul percorso proprietario dell’ordine finché la migrazione non viene completata e riconciliata. Per le offerte, il vecchio writer viene arrestato e riconciliato prima di abilitare il nuovo; una sola configurazione può pubblicare una data coppia account-canale e SKU.

## 8. Fonti ufficiali verificate

- [Amazon Selling Partner API — Orders API](https://developer-docs.amazon.com/sp-api/docs/orders-api)
- [eMAG Marketplace — API Documentation](https://marketplace.emag.ro/infocenter/emag-academy/how-to-add-a-product/product-import-through-api-or-feeds/api-documentation/?lang=en)
- [Temu Partner Platform — Seller Authorization Guide](https://partner.temu.com/documentation?menu_code=38e79b35d2cb463d85619c1c786dd303)
- [SellRapido — integrazione IBS–Feltrinelli](https://www.sellrapido.com/it/integrazioni/marketplace/come-vendere-su-ibs/)
- [SellRapido — marketplace integrati](https://www.sellrapido.com/it/integrazioni/marketplace/)

Le fonti confermano l’esistenza dei percorsi indicati, non l’abilitazione di uno specifico account HAPA. Endpoint, versioni, quote e permessi vengono riverificati all’avvio di ogni integrazione.
