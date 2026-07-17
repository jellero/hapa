# Roadmap HAPA

Ultimo riesame: 17 luglio 2026.

La roadmap segue vertical slice di business. HAPA decide e conserva lo stato commerciale; Automation esegue le integrazioni. Nessun job provider viene abilitato globalmente prima del pilot della singola capacità.

## Baseline

- [x] runtime HTTP/CLI, PostgreSQL, Redis, Docker e CI;
- [x] dominio ordine e outbox transazionale;
- [x] modelli iniziali cliente, prodotto, ricarico e spedizione;
- [x] runtime Automation fisicamente separato;
- [x] RabbitMQ e contratto ordine v1 verificati HAPA → Automation;
- [x] diagrammi architetturali recuperati e riallineati;
- [x] decisione HAPA system of record e Space fornitore;
- [x] schema additivo per account marketplace, offerte fornitore, acquisti, storico cliente, colli e label.

## P0 — Sicurezza, configurazione provider e messaggistica bidirezionale

- [ ] unire e distribuire il consumer HAPA con inbox idempotente;
- [ ] autenticazione, sessioni, autorizzazione deny-by-default e CSRF;
- [ ] audit delle azioni commerciali e degli accessi sensibili;
- [ ] metriche su inbox, outbox, dead letter e consumer lag;
- [ ] contratti v2 producer/consumer nei due repository;
- [ ] storage sicuro per etichette e documenti;
- [ ] CRUD amministrativo per account Space, SellRapido, GLS e provider futuri;
- [ ] configurazione da UI di ambiente, endpoint, account, cataloghi, contratti, capacità, mapping, frequenze e batch;
- [ ] campi credenziale write-only, mascherati e delegati allo storage cifrato di Automation senza persistenza in HAPA;
- [ ] azioni UI separate per test connessione, rotazione/revoca, abilitazione e disabilitazione per capacità;
- [ ] mostrare stato connessione, ultima verifica, scadenza token, checkpoint ed errore redatto senza esporre segreti;
- [ ] versionare e auditare ogni modifica di configurazione e ogni attivazione produzione.

Il confine completo è in [`PROVIDER_CONFIGURATION.md`](PROVIDER_CONFIGURATION.md).

**Gate:** nessun dato reale o comando provider è attivabile senza identità, permesso, deduplica, osservabilità, configurazione validata e test connessione riuscito. Nessun segreto transita su RabbitMQ o viene restituito all'interfaccia.

## P1 — Catalogo Space → HAPA

- [x] implementare producer normalizzato `space.catalog.item.observed` in Automation;
- [x] implementare consumer RabbitMQ e caso d'uso HAPA;
- [x] separare prodotto da offerta Space per tutte le nuove osservazioni;
- [x] creare prodotti nuovi inattivi con revisione manuale e deduplica;
- [ ] backfill e riconciliazione dei campi Space legacy;
- [ ] mostrare costo, disponibilità, versione ed età del dato;
- [ ] pilot read-only su Space.

**Gate:** una variazione Space aggiorna HAPA una sola volta, non regredisce con eventi fuori ordine e un prodotto nuovo non viene pubblicato prima dell'approvazione.

## P2 — Prodotti e offerte HAPA → SellRapido → IBS/marketplace

SellRapido è il connettore tecnico corrente che gestisce IBS. HAPA resta proprietario di prodotto, prezzo e quantità desiderati; SellRapido distribuisce tali dati ai canali downstream.

- [ ] configurare da UI account SellRapido, catalogo, UUID tecnico, marketplace/canale downstream e capacità abilitate;
- [ ] ottenere e verificare un'utenza API dedicata con ACL per lettura ordini e, quando richiesta, modifica prodotti;
- [ ] congelare endpoint e payload reali V2, chiarendo la differenza fra `/api/v2/product` e gli esempi `/api/product/{uuid}` presenti nella guida;
- [ ] CRUD ricarichi autorizzato e auditato;
- [ ] calcolo deterministico con costo, ricarico, fee, IVA e arrotondamento approvati;
- [ ] identità prodotto remota stabile `integration_account_id + catalog_id + sku`;
- [ ] comando `marketplace.product.upsert.requested` per anagrafica, immagini, attributi e campi approvati;
- [ ] comando `marketplace.offer.publish.requested` per prezzo e quantità finali già decisi da HAPA;
- [ ] adapter SellRapido `POST`/`PATCH` con batch massimo 1000, esiti parziali per indice e idempotenza applicativa;
- [ ] usare cataloghi data-entry oppure configurare `fields_lock` per impedire che import esterni sovrascrivano i campi gestiti da HAPA;
- [ ] riconciliazione mediante `GET /api/v2/product`, rispettando il limite di una richiesta ogni 120 secondi;
- [ ] rispettare il limite di una scrittura prodotti ogni 300 secondi aggregando le modifiche senza perdere l'ultima versione;
- [ ] pilot su un sottoinsieme SKU e un solo account SellRapido;
- [ ] cutover del writer precedente e verifica che esista un solo writer per catalogo/campo.

**Gate:** HAPA riproduce i valori inviati; SellRapido viene aggiornato da un solo writer; nessun import automatico sovrascrive silenziosamente i campi HAPA; errori parziali sono riconciliati per singolo SKU.

## P3 — Ordini SellRapido → HAPA

- [ ] configurare stati importati, frequenza, overlap, pagina e account dalla UI;
- [ ] autenticazione iniziale una sola volta, rinnovo access token e rotazione refresh token gestiti da Automation;
- [ ] import JSON incrementale con `GET /api/v2/order`, filtri `modified`, `offset` e `limit`;
- [ ] verificare con test contrattuali i nomi effettivi dei parametri, perché la guida alterna camelCase e snake_case;
- [ ] usare un watermark su `modified` con finestra di sovrapposizione e non avanzarlo prima dell'outbox durevole;
- [ ] `marketplace.order.observed` idempotente con `connector = sellrapido`;
- [ ] identità tecnica `integration_account_id + head.id`, conservando anche `code`, `marketplace_code`, `channel_code` e identificativi riga;
- [ ] mappare senza perdita gli stati SellRapido `standby`, `accepted`, `sent` e `cancelled`;
- [ ] cliente, identità, snapshot indirizzi, dati fiscali necessari e storico;
- [ ] snapshot economici di testata e righe, valuta, fee, spedizione e pagamento;
- [ ] minimizzare e non conservare per default `upload_status.request_payload` e token marketplace grezzi;
- [ ] revisione manuale per anomalie, righe senza SKU, collisioni e modifiche regressive;
- [ ] riconciliazione iniziale con ordini SellRapido esistenti e deduplica del backfill.

**Gate:** lo stesso ordine SellRapido non crea duplicati; aggiornamenti fuori ordine non fanno regredire HAPA; i dati storici restano ricostruibili; il checkpoint avanza soltanto dopo persistenza dell'osservazione.

## P4 — Acquisto HAPA → Space

L'integrazione avviene tramite una nuova API PHP sviluppata nel sistema Space. L'API è l'unico componente autorizzato a leggere e scrivere il database Space esistente; HAPA Automation non accede direttamente a PostgreSQL Space.

La tabella operativa corrente per le righe d'ordine è `public.ordini_articoli`. L'API Space inserisce gli ordini in tale modello e restituisce a HAPA Automation identificativi, quantità e stati letti dal database corrente.

- [ ] definire e versionare il contratto HTTP dell'API PHP Space per creazione ordine, lettura dettaglio e consultazione avanzamento;
- [ ] implementare autenticazione servizio-servizio, autorizzazione, TLS, rate limit, audit e rotazione credenziali dell'API Space;
- [ ] definire una chiave di idempotenza per impedire inserimenti duplicati in `ordini_articoli` quando HAPA Automation ritenta la creazione;
- [ ] definire la risposta di creazione con almeno identificativo ordine Space, identificativi delle righe e correlazione con ordine e righe HAPA;
- [ ] documentare quali tabelle e procedure Space devono essere usate per creare correttamente testata e righe, senza bypassare trigger o invarianti esistenti;
- [ ] congelare payload reali redatti dell'API Space per creazione ordine, lettura dettaglio e avanzamento stato;
- [ ] censire valori e semantica effettiva dei campi `stato_os`, `stato_bo`, `stato_sh`, `stato_de`, `stato_arc_sh`, `stato_arc_de` e `closing_order`;
- [ ] chiarire quali campi rappresentano preso in carico, in lavorazione, pronto, non disponibile, spedito, archiviato o altre condizioni operative;
- [ ] distinguere stato della testata, stato delle singole righe, quantità ordinata, disponibile, evasa, spedita e residua;
- [ ] conservare nella risposta API sia i valori grezzi Space sia lo stato normalizzato e la versione del mapping applicato;
- [ ] definire un mapping versionato dai valori del database Space agli stati canonici HAPA, senza dedurlo dai soli nomi delle colonne;
- [ ] definire la matrice delle transizioni ammesse e il trattamento di risposte duplicate, tardive, regressive o incoerenti;
- [ ] completare aggregato e repository `SupplierPurchaseOrder` senza riutilizzare gli stati dell'ordine di vendita;
- [ ] rappresentare in modo esplicito le fasi richiesto, preso in carico, in lavorazione, parzialmente disponibile, pronto, completato, rifiutato e non disponibile dopo la validazione delle regole Space;
- [ ] comando `space.purchase_order.submit.requested` con versione e idempotency key applicativa;
- [ ] adapter HAPA Automation idempotente che chiama l'API Space per inserire l'ordine e recuperarne lo stato;
- [ ] polling dell'API Space con checkpoint persistente, frequenza controllata e riconciliazione periodica;
- [ ] evento `space.purchase_order.status_changed` idempotente con versione sorgente, istante osservato, valori grezzi e dettaglio righe;
- [ ] gestione separata di indisponibilità totale, indisponibilità parziale e sostituzioni, sempre secondo una policy commerciale HAPA esplicita;
- [ ] riconciliazione tramite API dopo timeout ambiguo prima di qualsiasi nuovo inserimento dell'ordine;
- [ ] audit completo di richiesta, risposta redatta, stato precedente, stato nuovo, causazione e decisioni manuali;
- [ ] UI acquisti con timeline degli stati Space, quantità per riga, eccezioni e azioni consentite;
- [ ] test contrattuali tra HAPA Automation e API Space, test di idempotenza inserimento, test delle transizioni e pilot controllato.

**Gate:** HAPA Automation non accede direttamente al database Space; ogni creazione è idempotente; ordine e righe Space sono correlati stabilmente con HAPA; ogni combinazione di stato usata è documentata e verificata; una risposta regressiva non modifica l'acquisto; una indisponibilità non chiude o modifica automaticamente la vendita senza una policy esplicita.

## P5 — Picking, GLS ed etichetta

La vertical slice usa il GLS Web Integrated Labeling Service SOAP/XML. La discovery corrente deriva dal manuale MU.162 Rev.20 del 1 ottobre 2021: prima del codice produttivo devono essere verificati con GLS l'attivazione del servizio, il WSDL effettivo, i contratti abilitati e l'ambiente di collaudo.

- [ ] configurare da UI endpoint/WSDL, ambiente, sede, cliente, contratto, password write-only, aggregazione, formato label e capacità;
- [ ] completare picking, quantità finali, colli, peso reale e dimensioni prima di richiedere la spedizione;
- [ ] modellare separatamente spedizione HAPA, colli HAPA, riferimenti GLS e documenti label;
- [ ] introdurre gli stati applicativi almeno `requested`, `open`, `awaiting_close`, `closed`, `failed`, `manual_review` e `cancelled`, senza comprimere la spedizione in un singolo flag;
- [ ] comando `shipping.shipment.create.requested` con versione, destinatario, servizio, colli, peso, contrassegno eventuale e idempotency key;
- [ ] adapter Automation GLS basato su `AddParcel`, che registra la spedizione in stato GLS aperto/in attesa di chiusura e restituisce tracking, progressivi collo e informazioni label;
- [ ] evento `shipping.shipment.opened` distinto dalla chiusura: la presenza del tracking non implica ancora affidamento consolidato alla rete GLS;
- [ ] comando `shipping.shipment.close.requested` separato, eseguito preferibilmente con `CloseWorkDayByShipmentNumber` sui numeri di spedizione già ottenuti;
- [ ] evento `shipping.shipment.closed` soltanto dopo esito GLS `OK` e riconciliazione dello stato chiuso;
- [ ] rappresentare ogni collo con un `ContatoreProgressivo` GLS univoco e non nullo, conservandone la correlazione stabile con `shipment_package` HAPA;
- [ ] usare `Bda` e/o `RiferimentoCliente` come riferimenti applicativi controllati, senza considerarli da soli una garanzia di idempotenza provider;
- [ ] definire e collaudare la configurazione di aggregazione GLS, preferendo destinatario + BDA o altra regola concordata che eviti accorpamenti accidentali;
- [ ] supportare il multicollo come un elemento `Parcel` per ciascun collo, verificando aggregazione, numerazione progressiva e limite massimo previsto dal contratto;
- [ ] scegliere la strategia label PDF/ZPL: ritorno immediato da `AddParcel` oppure recupero tecnico mediante `GetPdf`/`GetZpl` senza ricreare la spedizione;
- [ ] salvare label, formato, checksum, riferimento GLS, progressivo collo, data di generazione e retention in storage sicuro; nessun binario transita su RabbitMQ o nei log;
- [ ] consentire stampa e ristampa autorizzate dalla UI recuperando il documento esistente o rigenerandolo dal progressivo, senza una nuova `AddParcel`;
- [ ] implementare riconciliazione con `ListSpedPeriod`/`ListSpedPeriodByStato` e stati GLS `in attesa di chiusura`/`chiuso` prima di ripetere una chiamata con esito ambiguo;
- [ ] classificare errori di autenticazione/configurazione, validazione dati, instradamento `GLS CHECK`, indisponibilità tecnica ed esito ambiguo con retry policy differenti;
- [ ] validare prima della chiamata almeno indirizzo, località, CAP, provincia, peso > 0, numero colli, contrassegno, assicurazione e servizi accessori;
- [ ] trattare `DeleteSped` come cancellazione del record Label Service, non come garanzia di fermare una spedizione già chiusa e inoltrata nel circuito GLS;
- [ ] mantenere `PickUpRequest`, `DeletePickUp` e `ReleaseShipmentStock` come capacità separate dalla creazione/chiusura spedizione, da attivare solo con casi d'uso e autorizzazioni dedicati;
- [ ] auditare richiesta redatta, risposta redatta, numero spedizione, stato precedente/nuovo, progressivi collo, tentativi, errori e decisioni manuali;
- [ ] test contrattuali SOAP/XML, fixture di risposte reali redatte, test multicollo, timeout dopo `AddParcel`, timeout dopo chiusura, ristampa e pilot controllato GLS.

**Gate:** una richiesta logica non crea più spedizioni GLS; `open` e `closed` restano distinti; un timeout viene riconciliato prima del retry; ogni collo mantiene il proprio progressivo e la propria label; una ristampa non crea una nuova spedizione; la chiusura HAPA avviene soltanto dopo conferma GLS verificata.

## P6 — Fulfilment HAPA → SellRapido e chiusura

- [ ] definire mapping esplicito fra stato HAPA e stati SellRapido `standby`, `accepted`, `sent`, `cancelled`;
- [ ] comando `marketplace.fulfilment.publish.requested` con ID tecnico SellRapido, tracking, corriere e note;
- [ ] adapter `POST /api/v2/order/status` con aggiornamenti massivi e correlazione degli errori per `index` e `id`;
- [ ] usare `courier_code` configurato e validare la coppia tracking/corriere prima dell'invio;
- [ ] trattare la risposta vuota come successo/no-op e ogni elemento di errore come esito parziale da riconciliare;
- [ ] in caso di richiesta precedente pendente, non duplicare l'aggiornamento: rileggere l'ordine e ritentare con backoff;
- [ ] non modellare rimozione di tracking, corriere o data pagamento perché l'API consente solo aggiunta/aggiornamento;
- [ ] verifica tracking, quantità e stato GLS chiuso prima di inviare `sent`;
- [ ] read model di stato complessivo SellRapido, marketplace downstream, acquisto e spedizione;
- [ ] riconciliazione tramite `GET /api/v2/order` dopo timeout o errore ambiguo;
- [ ] chiusura ordine solo quando vendita, acquisto, spedizione e fulfilment SellRapido sono coerenti.

**Gate:** lo stesso fulfilment non viene pubblicato due volte; gli errori parziali non nascondono i successi; un timeout viene riconciliato prima del retry; HAPA non chiude l'ordine finché SellRapido non riflette tracking e stato attesi.

## P7 — Storico cliente e operatività

- [ ] casi d'uso cliente e versioni append-only;
- [ ] merge, rettifica, anonimizzazione e retention;
- [ ] ricerca e timeline completa;
- [ ] export autorizzato e auditato;
- [ ] backup/restore e prove di continuità.

## P8 — Fiscalità

- [ ] validazione con commercialista e consulente;
- [ ] scelta canale diretto/intermediario;
- [ ] modello documento immutabile;
- [ ] fattura elettronica, ricevute, scarti e conservazione;
- [ ] corrispettivi secondo il processo approvato;
- [ ] riconciliazione e segregazione ruoli.

Nessun codice fiscale operativo viene introdotto prima dei gate in [`FISCAL.md`](FISCAL.md).

## Espansioni

Solo dopo la stabilizzazione Space/SellRapido/IBS/GLS:

1. ulteriori marketplace gestiti da SellRapido;
2. Temu diretto se necessario;
3. Amazon diretto se necessario;
4. BRT;
5. eventuale storefront diretto.

Ogni espansione riusa i contratti normalizzati e mantiene un solo writer per account e capacità.