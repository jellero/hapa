# Architettura del sistema HAPA

Ultimo riesame: 17 luglio 2026.

## 1. Scopo e contesto aziendale

HAPA è il gestionale della realtà commerciale separata fisicamente e amministrativamente da Space. HAPA acquista articoli da Space e li rivende sui marketplace. IBS è il canale attivo; Temu e Amazon sono pianificati. GLS è il primo corriere del flusso operativo; BRT è previsto successivamente.

La separazione da Space è un confine aziendale oltre che tecnico. Space è trattato come fornitore esterno: HAPA conserva il proprio catalogo commerciale, i propri acquisti, le proprie vendite, i clienti e la propria documentazione. Gli identificativi Space restano riferimenti esterni.

Il sistema è formato da due applicazioni:

- **HAPA**: gestionale e system of record del business;
- **HAPA Automation**: runtime tecnico delle integrazioni e delle attività asincrone.

## 2. Principi non negoziabili

1. Ogni dato di business ha un solo proprietario autorevole.
2. Prodotti, clienti, ordini di vendita, acquisti, spedizioni e documenti appartengono a HAPA.
3. Automation possiede soltanto esecuzione tecnica, cursori, retry, rate limit, idempotenza provider e proiezioni ricostruibili.
4. Nessun servizio legge o scrive il database dell’altro.
5. Space, marketplace e corrieri non determinano direttamente lo stato interno HAPA: producono osservazioni o esiti che HAPA valida e applica.
6. HAPA decide prezzi, ricarichi, quantità vendibili, acquisti, spedizioni e chiusura ordine.
7. Una modifica di dominio e il relativo messaggio sono atomici tramite transactional outbox.
8. Un messaggio ricevuto e la sua applicazione sono atomici tramite inbox.
9. Le chiamate con esito ambiguo vengono riconciliate prima di un nuovo tentativo.
10. I flussi vengono attivati per singolo account e capacità, iniziando da IBS.

## 3. Contesto del sistema

Il diagramma di contesto recupera e aggiorna lo schema storico rimosso durante la separazione dei repository.

```mermaid
flowchart TB
    Operator["Operatori HAPA"]
    Hapa["HAPA gestionale"]
    HapaDb[("PostgreSQL HAPA")]
    Redis[("Redis HAPA")]
    Broker["RabbitMQ · infrastruttura condivisa"]
    Automation["HAPA Automation"]
    AutomationDb[("PostgreSQL Automation")]
    Space["Space · fornitore"]
    IBS["IBS · marketplace attivo"]
    Temu["Temu · futuro"]
    Amazon["Amazon · futuro"]
    GLS["GLS"]
    BRT["BRT · futuro"]
    Fiscal["SdI / intermediario fiscale · futuro"]

    Operator <--> Hapa
    Hapa <--> HapaDb
    Hapa <--> Redis
    Hapa <--> Broker
    Broker <--> Automation
    Automation <--> AutomationDb
    Automation <--> Space
    Automation <--> IBS
    Automation -.-> Temu
    Automation -.-> Amazon
    Automation <--> GLS
    Automation -.-> BRT
    Automation -.-> Fiscal
```

RabbitMQ è condiviso come infrastruttura di trasporto, non come proprietario dei dati. In sviluppo può essere avviato dal Compose Automation; in produzione deve essere considerato una dipendenza infrastrutturale autonoma con ACL separate.

## 4. Bounded context HAPA

| Contesto | Responsabilità |
|---|---|
| Catalog | prodotto canonico HAPA, codici, descrizioni e stato commerciale |
| Supplier | offerta Space, costo di acquisto, disponibilità e ordini di acquisto |
| Pricing | ricarichi, costi di canale, arrotondamento, prezzo e quantità desiderati |
| Sales | ordini marketplace, righe economiche, pagamenti noti e ciclo commerciale |
| Customers | cliente canonico, identità esterne, indirizzi, versioni e storico |
| Fulfillment | disponibilità, picking, colli, spedizioni, label e tracking |
| Marketplace | canali, account venditore, offerte e stato applicativo delle pubblicazioni |
| Fiscal | fatture, note, corrispettivi, ricevute e conservazione futura |
| Identity & Audit | utenti, ruoli, autorizzazioni e audit |

I moduli `Space`, `Marketplace`, `Gls` e `Brt` presenti nel codice HAPA descrivono contratti applicativi e tipi normalizzati. I client e i protocolli provider appartengono ad Automation.

## 5. Decisione sui database

### 5.1 Prodotti

Il prodotto resta in HAPA perché è l’oggetto venduto dalla società HAPA e deve sopravvivere a cambi di fornitore o connettore. Il dato Space viene separato come **offerta fornitore**:

- `catalog_items`: identità commerciale HAPA;
- `supplier_catalog_items`: mapping Space, costo, disponibilità e versione osservata;
- `pricing_rules`: decisioni commerciali HAPA;
- `marketplace_offers`: prezzo e quantità desiderati per account-canale.

Automation può ricevere un comando di pubblicazione contenente già prezzo e quantità. Non deve ricevere le regole per ricalcolarle.

### 5.2 Ordini

L’ordine marketplace è un **ordine di vendita** HAPA. L’invio a Space crea un distinto **ordine di acquisto**. Non è corretto usare un solo stato per rappresentare entrambe le cose.

Esempio:

- vendita: `received`, `confirmed`, `in_fulfillment`, `shipped`, `closed`, `cancelled`;
- acquisto Space: `draft`, `requested`, `accepted`, `partially_available`, `ready`, `rejected`, `cancelled`;
- spedizione: `draft`, `requested`, `label_available`, `shipped`, `delivered`, `error`;
- fiscale futuro: stato separato dal ciclo logistico.

Il campo legacy `orders.status` viene mantenuto durante la migrazione, ma le nuove capacità non devono aggiungervi stati di acquisto, corriere o fatturazione.

### 5.3 Clienti e storico

HAPA conserva:

- profilo cliente corrente;
- identità esterne per account e canale;
- rubrica corrente;
- versioni append-only del profilo;
- snapshot immutabili di fatturazione e spedizione sull’ordine;
- audit delle fusioni, rettifiche, anonimizzazioni e accessi sensibili.

Automation tratta i dati personali soltanto per il tempo necessario alla chiamata provider e secondo retention esplicita.

### 5.4 Automation

Il database Automation conserva:

- inbox e outbox tecniche;
- job di polling e riconciliazione;
- checkpoint e watermark;
- operazioni provider e chiavi di idempotenza;
- tentativi, backoff, esiti redatti e riferimenti remoti;
- proiezioni minime ricostruibili.

Non conserva una seconda anagrafica commerciale.

Il modello completo è in [`DATA_MODEL.md`](DATA_MODEL.md).

## 6. Direzione dei messaggi

| Direzione | Tipo | Esempi |
|---|---|---|
| Automation → HAPA | osservazione provider | `space.catalog.item.observed`, `marketplace.order.observed` |
| HAPA → Automation | comando di business | `marketplace.offer.publish.requested`, `space.purchase_order.submit.requested`, `shipping.shipment.create.requested` |
| Automation → HAPA | esito provider | `marketplace.offer.published`, `space.purchase_order.accepted`, `shipping.shipment.created` |
| HAPA → Automation | evento necessario a una proiezione tecnica | `order.changed`, soltanto durante la transizione e per casi espliciti |

I job di polling (`sync Space`, `import IBS`, riconciliazione) nascono in Automation. Le azioni che modificano un rapporto commerciale (`ordina a Space`, `pubblica prezzo`, `crea spedizione`, `chiudi fulfilment`) nascono da un comando HAPA e non da un timer.

## 7. Catalogo e pubblicazione offerta

```mermaid
sequenceDiagram
    participant Job as "Job Automation"
    participant Space
    participant Auto as "HAPA Automation"
    participant Bus as RabbitMQ
    participant Hapa as HAPA
    participant DB as "PostgreSQL HAPA"
    participant IBS

    Job->>Space: leggi catalogo incrementale
    Space-->>Auto: prodotto, costo e disponibilità
    Auto->>Bus: space.catalog.item.observed
    Bus->>Hapa: evento versionato
    Hapa->>DB: prodotto + offerta Space + outbox
    Hapa->>Hapa: calcola prezzo e quantità IBS
    Hapa->>Bus: marketplace.offer.publish.requested
    Bus->>Auto: comando idempotente
    Auto->>IBS: pubblica offerta
    IBS-->>Auto: versione/esito remoto
    Auto->>Bus: marketplace.offer.published
    Bus->>Hapa: esito
    Hapa->>DB: stato offerta e versione remota
```

Il cursore Space avanza in Automation solo secondo una policy che impedisca la perdita dell’osservazione. Un dato fuori ordine non regredisce la versione applicata in HAPA.

## 8. Ordine, acquisto e spedizione

```mermaid
sequenceDiagram
    participant IBS
    participant Auto as "HAPA Automation"
    participant Bus as RabbitMQ
    participant Hapa as HAPA
    participant DB as "PostgreSQL HAPA"
    participant Space
    participant Operator as Operatore
    participant GLS

    Auto->>IBS: importa ordini dal watermark
    IBS-->>Auto: ordine e cliente
    Auto->>Bus: marketplace.order.observed
    Bus->>Hapa: evento idempotente
    Hapa->>DB: cliente + storico + vendita + righe
    Hapa->>DB: acquisto Space draft + outbox
    Hapa->>Bus: space.purchase_order.submit.requested
    Bus->>Auto: comando
    Auto->>Space: invia acquisto con idempotency key
    Space-->>Auto: riferimento ed esito
    Auto->>Bus: space.purchase_order.accepted
    Bus->>Hapa: esito acquisto
    Hapa->>DB: aggiorna acquisto e disponibilità
    Operator->>Hapa: picking, colli e conferma
    Hapa->>Bus: shipping.shipment.create.requested
    Bus->>Auto: comando
    Auto->>GLS: crea spedizione
    GLS-->>Auto: tracking ed etichetta
    Auto->>Bus: shipping.shipment.created
    Bus->>Hapa: esito spedizione
    Hapa->>DB: tracking + riferimento label
    Operator->>Hapa: stampa etichetta e chiude operazione
    Hapa->>Bus: marketplace.fulfilment.publish.requested
    Auto->>IBS: tracking e fulfilment
```

Il dettaglio degli errori e delle compensazioni è in [`BUSINESS_FLOWS.md`](BUSINESS_FLOWS.md).

## 9. Modello dati principale

```mermaid
erDiagram
    CUSTOMERS ||--o{ CUSTOMER_EXTERNAL_IDENTITIES : has
    CUSTOMERS ||--o{ CUSTOMER_ADDRESSES : has
    CUSTOMERS ||--o{ CUSTOMER_HISTORY : versions
    CUSTOMERS ||--o{ ORDERS : places
    MARKETPLACES ||--o{ MARKETPLACE_ACCOUNTS : exposes
    MARKETPLACE_ACCOUNTS ||--o{ ORDERS : receives
    ORDERS ||--|{ ORDER_LINES : contains
    CATALOG_ITEMS ||--o{ SUPPLIER_CATALOG_ITEMS : sourced_by
    CATALOG_ITEMS ||--o{ MARKETPLACE_OFFERS : offered_as
    CATALOG_ITEMS ||--o{ ORDER_LINES : snapshots
    ORDERS ||--o{ SUPPLIER_PURCHASE_ORDERS : procures
    SUPPLIER_PURCHASE_ORDERS ||--|{ SUPPLIER_PURCHASE_ORDER_LINES : contains
    ORDERS ||--o{ SHIPMENTS : fulfilled_by
    SHIPMENTS ||--|{ SHIPMENT_PACKAGES : contains
    SHIPMENTS ||--o{ SHIPMENT_LABELS : produces
    ORDERS ||--o{ FISCAL_DOCUMENTS : documented_by
```

Le entità fiscali sono pianificate e non vengono create finché commercialista, canale telematico, regole IVA e retention non sono formalmente approvati.

## 10. Ciclo HTTP HAPA

```mermaid
sequenceDiagram
    participant Client
    participant Proxy as "Reverse proxy"
    participant Nginx
    participant Kernel
    participant UseCase as "Caso d'uso"
    participant DB as PostgreSQL

    Client->>Proxy: HTTPS request
    Proxy->>Nginx: richiesta interna
    Nginx->>Kernel: public/index.php
    Kernel->>Kernel: identità, permessi, CSRF, correlation ID
    Kernel->>UseCase: input validato
    UseCase->>DB: transazione dominio + outbox + audit
    DB-->>UseCase: commit
    UseCase-->>Kernel: risultato
    Kernel-->>Client: risposta redatta
```

## 11. Topologia runtime

### Sviluppo

```mermaid
flowchart LR
    Browser --> Nginx
    Nginx --> PHP["PHP-FPM HAPA"]
    PHP --> HapaDb[("PostgreSQL HAPA")]
    PHP --> Redis[("Redis")]
    PHP <--> Broker["RabbitMQ"]
    Worker["Automation worker"] <--> Broker
    Worker --> AutoDb[("PostgreSQL Automation")]
    Worker --> Providers["Space / IBS / GLS"]
```

### Produzione

```mermaid
flowchart LR
    Internet --> Proxy["Reverse proxy / LB"]
    Proxy --> Web["HAPA Nginx + PHP-FPM"]
    Web --> HapaDb[("PostgreSQL HAPA")]
    Web --> Redis[("Redis HAPA")]
    Web <--> Broker["RabbitMQ gestito"]
    Automation["Automation workers"] <--> Broker
    Automation --> AutoDb[("PostgreSQL Automation")]
    Automation --> Providers["Space / marketplace / corrieri"]
    MigrationH["Migrazioni HAPA"] --> HapaDb
    MigrationA["Migrazioni Automation"] --> AutoDb
```

I PostgreSQL non condividono rete o credenziali. Il broker usa account separati per producer e consumer, routing key allowlisted, TLS fuori dal nodo locale e dead-letter queue osservabili.

## 12. Strategia di migrazione

La riorganizzazione è incrementale:

1. introdurre tabelle esplicite per account marketplace, offerta Space, acquisti, storico cliente, colli ed etichette;
2. preservare i dati nelle tabelle legacy;
3. scrivere le nuove vertical slice soltanto sul modello corretto;
4. backfill e riconciliare i dati esistenti;
5. bloccare le vecchie scritture;
6. rimuovere colonne o proiezioni legacy in una migrazione successiva e verificata.

La tabella HAPA `external_deliveries` diventa legacy: le nuove operazioni provider vengono registrate in `provider_operations` nel database Automation. Nessuna migrazione tenta un trasferimento cross-database implicito.

## 13. Fiscalità futura

Fatture elettroniche e corrispettivi appartengono a HAPA perché sono documenti e adempimenti della società HAPA. Automation potrà trasmettere file e ricevere notifiche, ma non numerare, modificare o ricostruire autonomamente i documenti.

Il modulo è descritto in [`FISCAL.md`](FISCAL.md). L’implementazione è bloccata fino alla validazione professionale e delle specifiche tecniche vigenti.

## 14. Stato implementativo

Implementato o disponibile:

- dominio ordine di vendita e persistenza transazionale;
- clienti e identità esterne iniziali;
- catalogo, ricarichi e offerte iniziali;
- outbox HAPA e runtime Automation separato;
- RabbitMQ con envelope versionato;
- test end-to-end iniziale HAPA → Automation.

Da riallineare:

- ordine di acquisto Space separato dalla vendita;
- account marketplace espliciti;
- snapshot economici completi delle righe;
- storico append-only cliente;
- comandi ed eventi con direzione corretta;
- rimozione delle decisioni commerciali dalle proiezioni Automation;
- vertical slice IBS, Space e GLS reali;
- autenticazione, autorizzazione e audit operativi;
- modulo fiscale futuro.
