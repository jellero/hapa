# Roadmap HAPA

Ultimo riesame: 19 luglio 2026.

La roadmap segue vertical slice di business. HAPA decide e conserva lo stato commerciale; Automation esegue le integrazioni. Nessun job provider viene abilitato globalmente prima del pilot della singola capacità.

Le direttive funzionali e di interfaccia del 19 luglio 2026 sono descritte in [`SYSTEM_NOTES.md`](SYSTEM_NOTES.md). Diagrammi, tabelle e decisioni esistenti devono essere integrati e mantenuti, non sostituiti o rimossi.

## Baseline

- [x] runtime HTTP/CLI, PostgreSQL, Redis, Docker e CI;
- [x] dominio ordine e outbox transazionale;
- [x] modelli iniziali cliente, prodotto, ricarico e spedizione;
- [x] runtime Automation fisicamente separato;
- [x] RabbitMQ verificato end-to-end in entrambe le direzioni: prodotto Space Automation → HAPA e ordine HAPA → Automation;
- [x] diagrammi architetturali recuperati e riallineati;
- [x] decisione HAPA system of record e Space fornitore;
- [x] schema additivo per account marketplace, offerte fornitore, acquisti, storico cliente, colli e label.
- [x] read model operativi per clienti e ordini con righe, acquisti Space, spedizioni, label e cronologie.

## P0 — Sicurezza, configurazione provider e messaggistica bidirezionale

- [x] unire e distribuire il consumer HAPA con inbox idempotente;
- [x] autenticazione, sessioni, autorizzazione deny-by-default e CSRF;
- [x] audit consultabile di accessi, logout, configurazione provider, regole di ricarico e revisione prodotti;
- [x] metriche aggregate autorizzate su dashboard HAPA per inbox, outbox, dead state e lag;
- [x] contratti comando v2 con factory/validatore HAPA e consumer/validatore Automation per tutte le operazioni provider correnti;
- [x] storage privato locale per etichette e documenti con riferimenti opachi, checksum e scrittura atomica;
- [x] CRUD amministrativo versionato dei dati non segreti per account Space, SellRapido, GLS e provider futuri;
- [x] configurazione da UI di ambiente, endpoint, account, cataloghi, contratti, capacità, mapping, frequenze e batch;
- [x] campi credenziale write-only, mascherati e delegati allo storage cifrato di Automation senza persistenza in HAPA;
- [x] sincronizzazione monotona e auditata della configurazione non segreta verso la proiezione Automation;
- [x] transizioni account separate con conferma produzione e gate su segreti, test connessione e versione Automation;
- [ ] azioni UI separate per test connessione, rotazione/revoca, abilitazione e disabilitazione per capacità;
- [ ] mostrare stato connessione, ultima verifica, scadenza token, checkpoint ed errore redatto senza esporre segreti;
- [x] versionare e auditare ogni modifica della configurazione non segreta; l’attivazione produzione resta bloccata fino al gate Automation.

Il confine completo è in [`PROVIDER_CONFIGURATION.md`](PROVIDER_CONFIGURATION.md).

**Gate:** nessun dato reale o comando provider è attivabile senza identità, permesso, deduplica, osservabilità, configurazione validata e test connessione riuscito. Nessun segreto transita su RabbitMQ o viene restituito all'interfaccia.

## P1 — Catalogo Space → HAPA

- [x] implementare producer normalizzato `space.catalog.item.observed` in Automation;
- [x] implementare consumer RabbitMQ e caso d'uso HAPA;
- [x] separare prodotto da offerta Space per tutte le nuove osservazioni;
- [x] creare prodotti nuovi inattivi con deduplica e revisione manuale approva/rifiuta da UI;
- [x] backfill paginato dei campi Space legacy tramite API PHP, con costo da listino HAPA, `album.onstock`, cursore e deduplica;
- [x] mostrare costo, disponibilità, versione ed età del dato;
- [ ] pilot read-only su Space.

**Gate:** una variazione Space aggiorna HAPA una sola volta, non regredisce con eventi fuori ordine e un prodotto nuovo non viene pubblicato prima dell'approvazione.

## P2 — Prodotti e offerte HAPA → SellRapido → IBS/marketplace

SellRapido è il connettore tecnico corrente che gestisce IBS. HAPA resta proprietario di prodotto, prezzo e quantità desiderati; SellRapido distribuisce tali dati ai canali downstream.

- [x] configurare da UI account SellRapido, catalogo, UUID tecnico, marketplace/canale downstream e capacità abilitate;
- [ ] ottenere e verificare un'utenza API dedicata con ACL per lettura ordini e, quando richiesta, modifica prodotti;
- [ ] congelare endpoint e payload reali V2, chiarendo la differenza fra `/api/v2/product` e gli esempi `/api/product/{uuid}` presenti nella guida;
- [x] CRUD ricarichi autorizzato, versionato, protetto da optimistic locking e auditato;
- [x] anteprima deterministica per marketplace con costo Space, regola di ricarico vincente, limiti e spiegazione dei blocchi di pubblicazione;
- [x] calcolo persistente HAPA di prezzo finale e quantità vendibile, ricalcolato su costo/stock Space, regole, approvazione e scorta di sicurezza;
- [ ] estendere il calcolo con fee, regime IVA e arrotondamenti approvati dai contratti reali dei canali;
- [ ] identità prodotto remota stabile `integration_account_id + catalog_id + sku`;
- [x] comando `marketplace.product.upsert.requested` per anagrafica, immagini, attributi e campi approvati;
- [x] comando `marketplace.offer.publish.requested` per prezzo e quantità finali già decisi da HAPA;
- [x] adapter SellRapido reale `POST`/`PATCH` per prezzo e quantità, esito per SKU, idempotenza applicativa, retry e rispetto del rate limit; batching fino a 1000 resta un'ottimizzazione;
- [ ] usare cataloghi data-entry oppure configurare `fields_lock` per impedire che import esterni sovrascrivano i campi gestiti da HAPA;
- [ ] riconciliazione mediante `GET /api/v2/product`, rispettando il limite di una richiesta ogni 120 secondi;
- [x] rispettare il limite di una scrittura prodotti ogni 300 secondi differendo le offerte successive senza perdere l'ultima versione;
- [x] pilot su un sottoinsieme SKU e un solo account SellRapido tramite `pilot_skus`;
- [ ] cutover del writer precedente e verifica che esista un solo writer per catalogo/campo.

**Gate:** HAPA riproduce i valori inviati; SellRapido viene aggiornato da un solo writer; nessun import automatico sovrascrive silenziosamente i campi HAPA; errori parziali sono riconciliati per singolo SKU.

## P3 — Ordini SellRapido → HAPA

- [x] configurare stati importati, frequenza, overlap, pagina e account dalla UI;
- [x] autenticazione iniziale, riuso della sessione cifrata e rinnovo access token tramite refresh token gestiti da Automation;
- [ ] rotazione preventiva del refresh token prima della scadenza dei 30 giorni;
- [x] import JSON incrementale con `GET /api/v2/order`, filtri configurabili `modified`, `offset` e `limit`;
- [ ] verificare con test contrattuali i nomi effettivi dei parametri, perché la guida alterna camelCase e snake_case;
- [x] usare un watermark su `modified` con finestra di sovrapposizione e non avanzarlo prima dell'outbox durevole;
- [x] `marketplace.order.observed` idempotente con `connector = sellrapido`;
- [x] identità tecnica `integration_account_id + head.id`, conservando anche `code`, `marketplace_code`, `channel_code` e identificativi riga;
- [x] conservare senza perdita gli stati SellRapido `standby`, `accepted`, `sent` e `cancelled`, portando in revisione le cancellazioni tardive;
- [x] cliente, identità, snapshot indirizzi, dati fiscali necessari e storico;
- [x] snapshot economici di testata e righe, valuta, fee e spedizione;
- [ ] completare lo snapshot pagamento dopo il congelamento dei campi effettivi del payload SellRapido;
- [x] minimizzare e non conservare per default `upload_status.request_payload` e token marketplace grezzi;
- [ ] revisione manuale per anomalie, righe senza SKU, collisioni e modifiche regressive;
- [x] deduplica del backfill tramite identità deterministica dell’osservazione e vincoli HAPA;
- [ ] riconciliazione iniziale con il volume completo degli ordini SellRapido esistenti.

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
- [ ] distinguere nel contratto quantità richiesta, quantità fisicamente resa disponibile al picking HAPA, quantità ancora attesa e quantità dichiarata non disponibile;
- [ ] conservare nella risposta API sia i valori grezzi Space sia lo stato normalizzato e la versione del mapping applicato;
- [ ] definire un mapping versionato dai valori del database Space agli stati canonici HAPA, senza dedurlo dai soli nomi delle colonne;
- [ ] definire la matrice delle transizioni ammesse e il trattamento di risposte duplicate, tardive, regressive o incoerenti;
- [ ] completare aggregato e repository `SupplierPurchaseOrder` senza riutilizzare gli stati dell'ordine di vendita;
- [ ] rappresentare in modo esplicito le fasi richiesto, preso in carico, in lavorazione, parzialmente disponibile, pronto, completato, rifiutato e non disponibile dopo la validazione delle regole Space;
- [x] comando `space.purchase_order.submit.requested` con versione e idempotency key applicativa;
- [x] generazione automatica e idempotente dell'acquisto Space da un ordine marketplace, con matching SKU/EAN, costi osservati, disponibilità e fallback `manual_review`;
- [x] recupero degli ordini marketplace preesistenti quando l'account Space diventa operativo, comando CLI di backfill e riprova autorizzata dal dettaglio ordine;
- [x] adapter HAPA Automation idempotente che chiama l'API Space per inserire l'ordine e acquisisce l'esito iniziale;
- [ ] polling dell'API Space con checkpoint persistente, frequenza controllata e riconciliazione periodica;
- [x] eventi iniziali `space.purchase_order.accepted` / `rejected` applicati idempotentemente in HAPA;
- [ ] evento di polling `space.purchase_order.status_changed` con versione sorgente, valori grezzi e dettaglio righe;
- [ ] includere negli eventi Space le quantità fisicamente rese disponibili, ancora attese e non disponibili per aggiornare un solo picking HAPA;
- [ ] gestione separata di indisponibilità totale, indisponibilità parziale e sostituzioni, sempre secondo una policy commerciale HAPA esplicita;
- [ ] riconciliazione tramite API dopo timeout ambiguo prima di qualsiasi nuovo inserimento dell'ordine;
- [ ] audit completo di richiesta, risposta redatta, stato precedente, stato nuovo, causazione e decisioni manuali;
- [x] UI ordine con acquisto automatico, account tecnico, stato ed errore operativo;
- [ ] timeline completa degli stati Space, quantità per riga, eccezioni e azioni consentite;
- [ ] test contrattuali tra HAPA Automation e API Space, test di idempotenza inserimento, test delle transizioni e pilot controllato.

**Gate:** HAPA Automation non accede direttamente al database Space; ogni creazione è idempotente; ordine e righe Space sono correlati stabilmente con HAPA; ogni combinazione di stato usata è documentata e verificata; una risposta regressiva non modifica l'acquisto; una indisponibilità non chiude o modifica automaticamente la vendita senza una policy esplicita.

## P5 — Picking, GLS ed etichetta

La vertical slice usa il GLS Web Integrated Labeling Service SOAP/XML. La discovery corrente deriva dal manuale MU.162 Rev.20 del 1 ottobre 2021: prima del codice produttivo devono essere verificati con GLS l'attivazione del servizio, il WSDL effettivo, i contratti abilitati e l'ambiente di collaudo.

- [x] configurare da UI endpoint/WSDL, ambiente, sede, cliente, contratto, password write-only, aggregazione, formato label e capacità;
- [x] registro spedizioni in sola lettura con ricerca, filtri, cliente, ordine, colli, peso, tracking e metadati label senza esporre riferimenti privati;
- [ ] completare picking, quantità finali, colli, peso reale e dimensioni prima di richiedere la spedizione;
- [ ] modellare separatamente spedizione HAPA, colli HAPA, riferimenti GLS e documenti label;
- [ ] introdurre gli stati applicativi almeno `requested`, `open`, `awaiting_close`, `closed`, `failed`, `manual_review` e `cancelled`, senza comprimere la spedizione in un singolo flag;
- [x] comando `shipping.shipment.create.requested` con versione, destinatario, servizio, colli, peso, contrassegno eventuale e idempotency key;
- [ ] adapter Automation GLS basato su `AddParcel`, che registra la spedizione in stato GLS aperto/in attesa di chiusura e restituisce tracking, progressivi collo e informazioni label;
- [ ] evento `shipping.shipment.opened` distinto dalla chiusura: la presenza del tracking non implica ancora affidamento consolidato alla rete GLS;
- [x] comando `shipping.shipment.close.requested` separato, eseguito preferibilmente con `CloseWorkDayByShipmentNumber` sui numeri di spedizione già ottenuti;
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
- [x] comando `marketplace.fulfilment.publish.requested` con ID tecnico SellRapido, tracking, corriere e note;
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

- [x] creazione, aggiornamento e archiviazione del profilo cliente con optimistic locking, versioni append-only e audit redatto;
- [ ] merge, rettifica, anonimizzazione e retention;
- [x] ricerca autorizzata e scheda aggregata con profilo, identità, indirizzi, ordini e versioni storiche;
- [ ] paginazione completa e timeline unificata per volumi elevati;
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

## P9 — Catalogo, pubblicazione e interfaccia operativa

Il dettaglio completo è in [`SYSTEM_NOTES.md`](SYSTEM_NOTES.md).

### P9.1 — Filtri di pubblicazione marketplace

- [ ] introdurre policy di catalogo versionate e auditate con azione `include` o `exclude`;
- [ ] supportare criteri su SKU, EAN, artista, titolo, formato, nome prodotto, marketplace, stato commerciale, stock e backorder;
- [ ] supportare almeno gli operatori uguale, diverso, contiene, non contiene, prefisso, suffisso, appartiene a elenco e relative negazioni;
- [ ] permettere, per esempio, di includere o escludere tutti gli SKU che contengono `223A`, incluso lo SKU `223A3432233`;
- [ ] permettere inclusioni ed esclusioni equivalenti su artista, titolo e formato;
- [ ] comporre criteri con modalità `tutti` o `almeno uno`, priorità, validità temporale e motivazione;
- [ ] rendere deterministica la precedenza fra inclusioni ed esclusioni e mostrare il percorso decisionale nell'anteprima;
- [ ] applicare le policy al catalogo o a sottoinsiemi dichiarativi, evitando una gestione manuale regola-per-singolo-SKU;
- [ ] impedire l'emissione dei comandi marketplace quando una policy esclude il prodotto;
- [ ] aggiungere test unitari, integrazione e fixture per conflitti, priorità e criteri testuali.

### P9.2 — Catalogo commerciale e stock Space

- [ ] estendere ricerca e filtri catalogo a SKU, EAN, artista, titolo, formato e nome prodotto;
- [ ] permettere dalla scheda prodotto modifica consentita, approvazione, rifiuto, blocco, sblocco, scorta di sicurezza e ricalcolo;
- [ ] mantenere separate scheda prodotto e politiche di catalogo;
- [ ] mostrare in tabella costo Space, stock immediato, backorder, vendibile HAPA, versione, revisione, età dato, offerte e stato pubblicazione;
- [ ] aggiungere indicatori separati per prodotti con prezzo, stock immediato, backorder, regole di ricarico e pubblicazioni obsolete o bloccate;
- [ ] rendere configurabile per account la soglia di obsolescenza del dato, con valore iniziale rappresentabile in 24 ore;
- [ ] mostrare quale regola di ricarico vince secondo la precedenza marketplace + selettore, selettore prodotto, marketplace e globale;
- [ ] mostrare nell'anteprima costo, regola vincente, regole escluse e motivazioni di blocco;
- [ ] mantenere fee, IVA e arrotondamenti fuori dal prezzo finale finché i contratti ufficiali non sono validati.

### P9.3 — Login e identità aziendale

- [ ] presentare la schermata come **Portale operativo HAPA** con sottotitolo **Accesso ai servizi aziendali**, chiarendo che HAPA è l'azienda e non il nome commerciale del software;
- [ ] usare testo neutro e sobrio per credenziali aziendali, assistenza e accesso riservato, senza introdurre la parola “istituzionale” come requisito o contenuto visibile;
- [ ] sostituire le etichette con **Email aziendale**, **Password**, **Ricorda questo dispositivo**, **Accedi** e **Password dimenticata?**;
- [ ] rimuovere claim promozionali, descrizioni operative, breadcrumb, “Centro operativo” e “Ogni ordine. Un solo controllo.”;
- [ ] mantenere ambiente development e correlation ID soltanto come informazioni tecniche discrete a fondo pagina;
- [ ] verificare accessibilità, gestione errori e comportamento responsive del login.

### P9.4 — Picking alimentato dall'evasione fisica Space

- [ ] creare o aggiornare un solo picking per ordine in base alle quantità fisicamente rese disponibili da Space, non in base al solo stock teorico di catalogo;
- [ ] acquisire per ogni riga quantità richiesta, quantità resa disponibile al picking HAPA, quantità ancora attesa e quantità dichiarata non disponibile;
- [ ] fare aggiornare HAPA da HAPA Automation mediante eventi idempotenti e non regressivi;
- [ ] ricontrollare con frequenza configurabile le righe ancora attese o in backorder senza creare picking duplicati;
- [ ] distinguere `ready_complete`, quando tutte le unità sono disponibili, da `partial_waiting_space`, quando alcune unità devono ancora arrivare;
- [ ] mantenere `partial_waiting_space` fuori dalla lavorazione ordinaria fino a un nuovo aggiornamento Space o a una decisione esplicita;
- [ ] distinguere `partial_action_required`, quando Space dichiara quantità non disponibili e serve una gestione manuale del parziale;
- [ ] permettere sul parziale non disponibile decisioni motivate e auditate come riduzione quantità, annullamento riga, annullamento ordine, ulteriore attesa o revisione;
- [ ] separare nella pagina generale pronti completi, parziali in attesa, parziali da gestire, in lavorazione, completati e anomalie;
- [ ] introdurre almeno gli stati `waiting_space`, `partial_waiting_space`, `ready_complete`, `partial_action_required`, `in_progress`, `completed`, `completed_partial`, `manual_review`, `shipment_requested`, `label_available` e `closed`;
- [ ] configurare timezone, orario limite giornaliero, giorni operativi e comportamento alla scadenza per il carico corriere;
- [ ] usare come configurazione iniziale possibile le ore `15:00`, senza renderle un valore fisso nel codice;
- [ ] non rendere lavorabile automaticamente al cutoff un picking che attende ancora merce Space;
- [ ] mostrare nella lavorazione EAN, SKU, artista, titolo, formato, quantità richiesta, disponibile, ancora attesa, non disponibile, da preparare, sparata e residua;
- [ ] incrementare la colonna **Sparati** a ogni lettura barcode valida;
- [ ] rendere verde la riga quando il numero di barcode sparati raggiunge la quantità da preparare;
- [ ] mostrare il progresso complessivo del picking e feedback per barcode estraneo, eccedente, ambiguo o relativo a una riga ancora in attesa;
- [ ] impedire l'avvio ordinario della scansione per un picking `partial_waiting_space`;
- [ ] consentire chiusura completa o chiusura parziale soltanto dopo la risoluzione autorizzata delle quantità non disponibili;
- [ ] permettere dalla pagina picking la richiesta di spedizione e la visualizzazione, stampa e ristampa della lettera di vettura PDF;
- [ ] garantire che stampa e ristampa non creino una seconda spedizione;
- [ ] aggiungere fixture dimostrative per pronto completo, parzialmente scansionato, tutte righe verdi, parziale in attesa non lavorabile, parziale con indisponibilità, EAN errato e prossimo alla scadenza;
- [ ] aggiungere test di idempotenza sugli aggiornamenti Space e sulle scansioni ripetute.

### P9.5 — Azioni ordine e resi

- [ ] permettere dal dettaglio ordine la selezione o richiesta di emissione fattura, soggetta al futuro modulo fiscale;
- [ ] introdurre modifica controllata dell'ordine con optimistic locking e audit;
- [ ] introdurre annullamento motivato con matrice delle transizioni consentite;
- [ ] progettare il processo di reso come capacità separata successiva all'annullamento o alla consegna;
- [ ] non accorpare stati di vendita, acquisto Space, picking, spedizione, fattura e reso;
- [ ] mostrare nel dettaglio ordine quantità Space richieste, rese disponibili, ancora attese e non disponibili.

### P9.6 — Guida in linea

- [ ] aggiungere nella barra di navigazione un pulsante **Guida**;
- [ ] creare percorsi passo per passo per catalogo, prezzi, pubblicazione, ordini, picking, spedizioni, integrazioni, utenti e audit;
- [ ] mostrare contenuti coerenti con i permessi dell'utente;
- [ ] collegare ogni pagina alla relativa guida contestuale;
- [ ] versionare la guida insieme alle funzionalità e includere risoluzione delle anomalie.

### P9.7 — Utenti, ruoli e permessi

- [ ] sostituire il flusso “Invita utente” con creazione diretta e operativa dell'account;
- [ ] permettere all'amministratore di creare, sospendere, riattivare e disabilitare utenti;
- [ ] gestire credenziale iniziale sicura e cambio password al primo accesso quando configurato;
- [ ] permettere creazione e modifica dei ruoli;
- [ ] associare permessi granulari ai ruoli e più ruoli allo stesso utente;
- [ ] revocare sessioni attive e consultare audit e accessi;
- [ ] proteggere le modifiche con autorizzazione deny-by-default, CSRF, optimistic locking e audit;
- [ ] aggiungere test end-to-end del flusso amministrativo.

### P9.8 — Stato integrazioni e scheduling

- [ ] creare una vista unificata dello stato di tutti gli account e delle capacità provider;
- [ ] mostrare configurazione HAPA, versione Automation, stato credenziali, ultimo test, ultima esecuzione, ultimo errore redatto, checkpoint, lag e prossimo avvio;
- [ ] mostrare code pendenti, retry e dead state in forma aggregata e autorizzata;
- [ ] rendere gestibili le frequenze di catalogo Space, verifica degli ordini Space ancora attesi, ordini marketplace in entrata, acquisti Space in uscita, offerte marketplace, riconciliazioni, fulfilment e spedizioni;
- [ ] mantenere scheduling tecnico, lock, retry e rate limit in HAPA Automation;
- [ ] validare le frequenze rispetto ai limiti contrattuali e spiegare eventuali normalizzazioni o rifiuti;
- [ ] versionare e auditare ogni modifica di frequenza o capacità;
- [ ] introdurre una anteprima della prossima esecuzione prima del salvataggio.

### P9.9 — Backorder e disponibilità fisica Space

- [ ] rappresentare separatamente stock di catalogo, backorder e quantità fisicamente disponibile per uno specifico ordine;
- [ ] configurare la frequenza di ricontrollo delle righe ordine ancora attese per account Space;
- [ ] aggiornare lo stesso picking quando Space rende disponibili nuove unità senza creare duplicati;
- [ ] impedire a osservazioni regressive di ridurre quantità già confermate o scansionate;
- [ ] gestire separatamente quantità disponibile, ancora attesa e dichiarata non disponibile;
- [ ] mantenere fuori lavorazione il parziale ancora atteso;
- [ ] portare in gestione manuale il parziale dichiarato non disponibile senza applicare automaticamente annullamenti o riduzioni;
- [ ] mantenere l'ordine aperto finché acquisto, picking, spedizione e fulfilment non sono coerenti.

**Gate P9:** le regole massive operano sul catalogo con anteprima deterministica; il singolo prodotto resta ricercabile, modificabile e bloccabile; il login rappresenta HAPA come azienda con testo neutro; il picking riflette quantità fisicamente rese disponibili da Space e distingue parziale in attesa da parziale per indisponibilità; ogni scansione incrementa la colonna Sparati e completa visivamente la riga; label e ristampe non duplicano spedizioni; utenti e scheduling sono realmente amministrabili.

## Espansioni

Solo dopo la stabilizzazione Space/SellRapido/IBS/GLS:

1. ulteriori marketplace gestiti da SellRapido;
2. Temu diretto se necessario;
3. Amazon diretto se necessario;
4. BRT;
5. eventuale storefront diretto.

Ogni espansione riusa i contratti normalizzati e mantiene un solo writer per account e capacità.