# Modello dati e proprietà

Ultimo riesame: 17 luglio 2026.

## Decisione

PostgreSQL HAPA è il registro autorevole del business. PostgreSQL Automation è il registro autorevole dell’esecuzione tecnica. La duplicazione è ammessa soltanto per proiezioni ricostruibili e payload tecnici con retention controllata.

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

I prodotti scoperti per la prima volta da Space nascono con `active = false` e `onboarding_status = pending_review`. L’approvazione commerciale è esplicita: fino a quel momento non possono generare offerte marketplace. ID Space, EAN e SKU sono segnali di matching; se EAN e SKU puntano a prodotti differenti l’osservazione resta in `manual_review`.

## Marketplace

`marketplaces` identifica il canale. `marketplace_accounts` identifica l’account venditore e il connettore attivo. L’identità esterna è:

```text
marketplace_account_id + external_order_id
```

Questo evita collisioni quando lo stesso canale ha più account e rende possibile migrare da un connettore aggregatore a uno diretto senza cambiare l’identità commerciale.

IBS ha stato business `active`; Temu e Amazon sono `planned`. Lo stato business non abilita automaticamente un adapter: l’abilitazione tecnica resta un gate Automation separato.

## Vendita e acquisto

`orders` è la vendita al cliente. Per compatibilità mantiene il nome storico, ma non deve contenere stati del fornitore o del corriere.

`supplier_purchase_orders` è l’acquisto da Space. Un ordine di vendita può generare più acquisti, e un acquisto può contenere righe collegate alle righe vendute.

Le righe vendita devono conservare snapshot economici:

- SKU e descrizione venduti;
- quantità;
- prezzo unitario e totale;
- aliquota o natura IVA applicata;
- sconti e costi allocati quando introdotti;
- identificativo esterno della riga.

Gli importi sono interi in unità minori e la valuta è ISO 4217.

## Clienti e storico

Il profilo corrente resta in `customers`. `customer_history` conserva versioni append-only del profilo normalizzato. Gli indirizzi dell’ordine restano snapshot e non vengono riscritti quando cambia la rubrica.

La cancellazione applicativa non elimina automaticamente documenti, ordini o audit soggetti a retention. Anonimizzazione e conservazione sono casi d’uso espliciti e autorizzati.

## Spedizioni

`shipments` rappresenta la spedizione applicativa. Le nuove tabelle separano:

- `shipment_packages`: peso, dimensioni e riferimento collo;
- `shipment_labels`: formato, storage reference, checksum, generazione e retention.

Il binario dell’etichetta non deve essere scritto nei log o nei messaggi RabbitMQ. Il payload trasporta un riferimento temporaneo o durevole con accesso controllato.

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
| `inbox_messages` | deduplica dei comandi/eventi ricevuti |
| `outbox_messages` | pubblicazione affidabile verso exchange esplicito |
| proiezioni legacy | compatibilità temporanea, non nuove decisioni |

Le operazioni `publish offer`, `submit purchase order`, `create shipment` e `publish fulfilment` non sono job periodici: sono comandi HAPA.

## Regole di evoluzione

1. Migrazioni additive prima delle rimozioni.
2. Backfill idempotenti e verificabili.
3. Vincoli database sulle invarianti stabili.
4. Nessun enum provider-specifico nel nucleo se può essere un riferimento configurato.
5. Nessun payload grezzo senza retention e classificazione dati.
6. Nessun accesso cross-database.
7. Ogni read model deve dichiarare la propria sorgente e ricostruibilità.
