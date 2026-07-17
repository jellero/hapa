# Modello dati e proprietà

Ultimo riesame: 17 luglio 2026.

## Decisione

PostgreSQL HAPA è il registro autorevole del business. PostgreSQL Automation è il registro autorevole dell'esecuzione tecnica. La duplicazione è ammessa soltanto per proiezioni ricostruibili e payload tecnici con retention controllata.

## Catalogo e fornitore

`catalog_items` continua a rappresentare il prodotto canonico HAPA durante la migrazione. I campi Space presenti nella tabella sono legacy e vengono copiati nel nuovo `supplier_catalog_items`.

| Tabella | Contenuto |
|---|---|
| `catalog_items` | SKU HAPA, EAN, identità e stato del prodotto |
| `supplier_catalog_items` | supplier, ID e SKU Space, costo, disponibilità, versione e data osservata |
| `supplier_catalog_observations` | inbox/audit idempotente delle osservazioni Space e conflitti da revisionare |
| `pricing_rules` | regole commerciali HAPA |
| `marketplace_offers` | prezzo e quantità desiderati, stato applicativo e versione remota |

Il costo Space non è un “prezzo base” generico: è un costo di acquisto osservato presso un fornitore. Il prezzo di vendita deve conservare almeno costo utilizzato, regola applicata, versione del calcolo, valuta e arrotondamento.

I prodotti scoperti per la prima volta da Space nascono con `active = false` e `onboarding_status = pending_review`. L'approvazione commerciale è esplicita: fino a quel momento non possono generare offerte marketplace. ID Space, EAN e SKU sono segnali di matching; se EAN e SKU puntano a prodotti differenti l'osservazione resta in `manual_review`.

## Account di integrazione e configurazione

La configurazione target separa business, configurazione applicativa e segreti tecnici:

| Entità | Contenuto |
|---|---|
| `integration_accounts` | provider, ambiente, nome, stato desiderato e versione configurazione |
| `integration_account_capabilities` | capacità abilitate per account, ad esempio prodotti, ordini, fulfilment, spedizioni e label |
| `integration_account_settings` | endpoint, cataloghi, contratti, mapping, frequenze e altri valori non segreti validati per schema |
| `integration_secret_status` | riferimento opaco, versione, stato, ultima rotazione e verifica; non contiene il valore segreto |
| `marketplace_accounts` | account commerciale e canale downstream, collegato al connettore tecnico attivo |

HAPA possiede i valori non segreti e lo stato desiderato. Automation possiede password, client secret, token e materiale crittografico. La UI può sostituire un segreto tramite un campo write-only, ma HAPA non lo persiste e non può rileggerlo.

Ogni modifica di configurazione è versionata e auditata. L'abilitazione di un account o di una capacità è separata dal semplice salvataggio dei dati.

## Marketplace e SellRapido

`marketplaces` identifica il canale commerciale downstream. `marketplace_accounts` identifica l'account venditore e il connettore attivo. IBS è il canale corrente, servito tecnicamente da SellRapido.

Per un ordine importato via SellRapido si conservano due livelli di identità:

```text
integration_account_id + provider_order_id
marketplace_account_id + external_order_id
```

Il primo identifica senza collisioni il record tecnico SellRapido, normalmente `head.id`. Il secondo conserva l'identità commerciale del marketplace, normalmente derivata da `code` e dall'account downstream. HAPA conserva inoltre `marketplace_code`, `channel_code` e gli identificativi delle righe.

Questo consente di migrare da SellRapido a un connettore diretto senza riscrivere lo storico commerciale e impedisce collisioni quando più account o canali usano codici ordine simili.

L'identità remota dei prodotti SellRapido è:

```text
integration_account_id + catalog_id + sku
```

Catalogo, UUID tecnico e policy `fields_lock` sono configurazioni dell'account. Le risposte per indice delle scritture massive devono essere correlate alla singola versione prodotto/offerta HAPA.

## Vendita e acquisto

`orders` è la vendita al cliente. Per compatibilità mantiene il nome storico, ma non deve contenere stati del fornitore o del corriere.

`supplier_purchase_orders` è l'acquisto da Space. Un ordine di vendita può generare più acquisti, e un acquisto può contenere righe collegate alle righe vendute.

Le righe vendita devono conservare snapshot economici:

- SKU e descrizione venduti;
- quantità;
- prezzo unitario e totale;
- aliquota o natura IVA applicata;
- sconti e costi allocati quando introdotti;
- identificativo esterno della riga.

Gli importi sono interi in unità minori e la valuta è ISO 4217.

Gli stati provider `standby`, `accepted`, `sent` e `cancelled` non vengono usati direttamente come enum del nucleo: sono valori osservati, mappati a stati HAPA tramite una versione di mapping conservata.

## Clienti e storico

Il profilo corrente resta in `customers`. `customer_history` conserva versioni append-only del profilo normalizzato. Gli indirizzi dell'ordine restano snapshot e non vengono riscritti quando cambia la rubrica.

La cancellazione applicativa non elimina automaticamente documenti, ordini o audit soggetti a retention. Anonimizzazione e conservazione sono casi d'uso espliciti e autorizzati.

Payload provider grezzi come `upload_status.request_payload` e token marketplace non sono dati business necessari: vengono esclusi o conservati cifrati con retention esplicita soltanto per diagnosi autorizzate.

## Spedizioni

`shipments` rappresenta la spedizione applicativa. Le nuove tabelle separano:

- `shipment_packages`: peso, dimensioni e riferimento collo;
- `shipment_labels`: formato, storage reference, checksum, generazione e retention.

Il binario dell'etichetta non deve essere scritto nei log o nei messaggi RabbitMQ. Il payload trasporta un riferimento temporaneo o durevole con accesso controllato.

Il fulfilment SellRapido conserva provider order ID, stato richiesto, tracking, corriere, tentativo, esito e versione HAPA. Un aggiornamento parzialmente fallito non modifica le righe riuscite e resta riconciliabile per indice.

## Fiscalità

Il modello fiscale non viene anticipato con tabelle generiche. Prima della migrazione servono:

- decisione tra invio diretto e intermediario;
- numerazione e sezionali;
- regole IVA e natura operazioni;
- eventi che rendono il documento emettibile;
- conservazione e immutabilità;
- gestione scarti, ricevute, note di variazione e riconciliazione.

Vedere [`FISCAL.md`](FISCAL.md).

## Database Automation

Automation conserva:

| Tabella | Contenuto |
|---|---|
| `automation_jobs` | solo polling, webhook recovery e riconciliazioni |
| `provider_checkpoints` | cursori e watermark |
| `provider_operations` | idempotenza, tentativi, esito e riferimento remoto |
| `provider_secrets` o secret reference | segreti cifrati/riferimenti e token tecnici, mai leggibili da HAPA |
| `inbox_messages` | deduplica dei comandi/eventi ricevuti |
| `outbox_messages` | pubblicazione affidabile verso exchange esplicito |
| proiezioni legacy | compatibilità temporanea, non nuove decisioni |

Le operazioni `publish product`, `publish offer`, `submit purchase order`, `create shipment` e `publish fulfilment` non sono job periodici: sono comandi HAPA. L'import ordini SellRapido e le riconciliazioni sono job temporali.

## Regole di evoluzione

1. Migrazioni additive prima delle rimozioni.
2. Backfill idempotenti e verificabili.
3. Vincoli database sulle invarianti stabili.
4. Nessun enum provider-specifico nel nucleo se può essere un riferimento configurato.
5. Nessun payload grezzo senza retention e classificazione dati.
6. Nessun accesso cross-database.
7. Ogni read model deve dichiarare la propria sorgente e ricostruibilità.
8. Nessun segreto o token in HAPA, RabbitMQ, log o audit applicativo.
9. Ogni account e capacità provider è deny-by-default e versionato.