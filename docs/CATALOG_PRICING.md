# Catalogo, prezzi e disponibilità

Ultimo riesame: 16 luglio 2026.

## Scopo e stato

HAPA deve acquisire via API da Space il prezzo base e la disponibilità fisica degli articoli, applicare le policy commerciali interne e pubblicare via API prezzo finale e quantità vendibile sui marketplace abilitati.

La foundation è implementata:

- modulo `Catalog` e tipo monetario in unità minori, senza calcoli `float`;
- disponibilità vendibile calcolata come disponibilità Space meno scorta di sicurezza, con limite minimo zero;
- regole di ricarico percentuale, importo fisso o prezzo fisso;
- precedenza deterministica, priorità, prezzo minimo e prezzo massimo;
- contratti incrementali `SpaceCatalogAdapter` e `MarketplaceOfferAdapter`;
- schema PostgreSQL per articoli, regole e stato delle pubblicazioni;
- versione sorgente, versione HAPA, cursore scheduler e idempotency key;
- job `sync_space_catalog` e `publish_marketplace_offers`, creati disabilitati;
- pagina `/ui/catalog` e aggiornamento delle aree Automazioni e Integrazioni.

Non sono implementati gli adapter HTTP reali, i repository applicativi, gli handler outbox, il read model autorizzato e il CRUD delle regole. Nessun prezzo o stock viene quindi letto da Space o inviato a un marketplace in questa fase.

## Ownership dei dati

| Dato | Sorgente autorevole | Regola |
|---|---|---|
| identificativo articolo Space | Space | HAPA conserva il mapping senza modificarlo |
| prezzo base | Space | un aggiornamento marketplace non lo sovrascrive |
| disponibilità fisica | Space | valore non negativo e versionato |
| scorta di sicurezza | HAPA | protegge da sovravendita e ritardi di propagazione |
| regole di ricarico | HAPA | modifiche autorizzate, versionate e auditate |
| prezzo finale | HAPA | derivato da prezzo Space e regola vincente |
| quantità vendibile | HAPA | `max(0, disponibilità Space - scorta di sicurezza)` |
| identificativo e versione offerta remota | marketplace | usati per riconciliazione, non come sorgente del prezzo HAPA |

Il marketplace è un destinatario della proiezione commerciale. Un valore letto in riconciliazione non diventa automaticamente autorevole: una divergenza genera ripubblicazione controllata o revisione manuale.

## Modello persistente

### `catalog_items`

Conserva SKU canonico, EAN opzionale, mapping Space, valuta, prezzo base in unità minori, disponibilità fisica, scorta di sicurezza, quantità vendibile generata dal database, versione Space e data dell’ultima sincronizzazione.

### `pricing_rules`

Una regola dichiara:

- codice e nome;
- ambito `global`, `marketplace`, `sku` o `marketplace_sku`;
- tipo `percentage`, `fixed_amount` o `fixed_price`;
- valore del ricarico;
- valuta;
- priorità;
- eventuale prezzo minimo e massimo;
- finestra di validità e stato abilitato.

Le nuove regole nascono disabilitate. Il database impedisce combinazioni incoerenti tra ambito, marketplace e SKU.

### `marketplace_offers`

Conserva una sola proiezione per articolo e account-canale marketplace: prezzo con valuta e quantità desiderati, regola applicata, versione HAPA, stato di pubblicazione, idempotency key, versione remota, ultimo esito ed errore redatto.

Gli stati sono `disabled`, `pending`, `syncing`, `synced` ed `error`. Il passaggio a `syncing` richiederà claim atomico o outbox; un errore temporaneo torna nel ciclo di retry, uno definitivo richiede revisione.

## Calcolo del prezzo

HAPA seleziona una sola regola vincente per evitare ricarichi involontariamente cumulativi. La precedenza è:

1. marketplace + SKU;
2. SKU;
3. marketplace;
4. globale.

A parità di ambito vince la priorità numerica maggiore; un ulteriore pareggio viene risolto dal codice regola, così il risultato resta riproducibile. Il ricarico percentuale usa basis point e arrotondamento half-up sull’unità minore. Minimo e massimo vengono applicati dopo il ricarico.

Una valuta diversa tra prezzo Space e regola è un errore di configurazione, non una conversione implicita. Sconti, cambi valuta, imposte e promozioni restano fuori da questa foundation e appartengono alla futura fase e-commerce B2C.

## Flusso previsto

1. `sync_space_catalog` legge una pagina incrementale dall’API Space.
2. L’adapter valida SKU, identificativo, valuta, prezzo, quantità, versione e timestamp.
3. HAPA aggiorna l’articolo soltanto se la versione sorgente è nuova o riconciliata.
4. PostgreSQL ricalcola la quantità vendibile sottraendo la scorta di sicurezza.
5. HAPA seleziona la regola valida per ogni account-canale e calcola il prezzo finale.
6. La variazione dell’offerta produce un’intenzione idempotente nello stesso transaction boundary.
7. `publish_marketplace_offers` invia prezzo e quantità tramite il percorso attivo, diretto o SellRapido.
8. HAPA salva identificativo, versione remota quando disponibile ed esito e riconcilia eventuali divergenze.

Il cursore Space avanza soltanto dopo il commit dell’intero batch. Una pagina parzialmente fallita non viene dichiarata completata. La chiave di pubblicazione deriva almeno da account-canale, SKU e versione HAPA, così un retry non duplica l’effetto.

## Account, canali e connettori

Amazon, eMAG, Temu e IBS restano canali di vendita; SellRapido resta un connettore aggregatore. Per ogni account-canale può esistere un solo percorso attivo di pubblicazione delle offerte, come già previsto per l’import ordini.

Il contratto `MarketplaceOfferAdapter` non presume che ogni provider supporti lo stesso livello di atomicità tra prezzo e quantità. La discovery deve verificare aggiornamento congiunto o separato, quote, granularità, tempi di propagazione, stato asincrono e meccanismi di riconciliazione.

## Failure mode e protezioni

| Caso | Comportamento richiesto |
|---|---|
| timeout Space prima della risposta | retry dal cursore confermato |
| timeout dopo una pagina Space | non avanzare il cursore senza commit |
| versione Space vecchia | ignorare e registrare la divergenza |
| prezzo o quantità non validi | rifiutare l’articolo e non pubblicarlo |
| regola incoerente | mantenere l’ultima offerta valida e segnalare configurazione |
| timeout marketplace dopo invio | riconciliare per idempotency key o versione prima del retry |
| quota provider esaurita | retry temporaneo rispettando `Retry-After` |
| errore definitivo su un’offerta | stato `error`, audit e revisione autorizzata |
| Space non disponibile a lungo | nessuna invenzione dello stock; applicare policy di stale data ancora da approvare |

La policy su dati Space scaduti deve essere definita prima dell’esercizio. L’opzione più prudente è sospendere o azzerare la quantità pubblicabile oltre una soglia, ma la soglia non viene scelta nel codice senza decisione operativa.

## Gate di attivazione

I due job restano disabilitati finché non sono disponibili:

1. specifiche API Space e marketplace congelate per versione;
2. account sandbox, credenziali in secret e allowlist host;
3. repository e casi d’uso transazionali con audit;
4. handler outbox, errori tipizzati, timeout e rate limit;
5. test su paginazione, duplicati, versioni fuori ordine e crash a metà batch;
6. test su rounding, minimo/massimo e precedenza delle regole;
7. riconciliazione dopo timeout ambiguo;
8. autorizzazione e CSRF per modificare ricarichi e scorta di sicurezza;
9. metriche su età dato Space, coda offerte, errori e divergenze;
10. pilot su un solo account-canale con arresto rapido.

## Relazione con il futuro B2C

Il futuro e-commerce potrà riutilizzare articolo canonico, quantità vendibile e motore prezzi come input. Restano comunque TODO distinti: varianti e contenuti, listini B2C, imposte, promozioni, carrello, checkout, pagamenti, resi e area cliente. La presenza del catalogo operativo non rende attivo alcuno storefront.
