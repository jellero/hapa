# Anagrafiche clienti e ordini

Ultimo riesame: 16 luglio 2026.

## Scopo e maturità

HAPA mantiene un’anagrafica cliente canonica e un’anagrafica ordine indipendente dal singolo canale. Questa base serve gli ordini marketplace attuali e prepara il futuro e-commerce B2C senza anticiparne catalogo, carrello, checkout o pagamenti.

Lo stato corrente è **parziale**:

- schema PostgreSQL, vincoli e indici implementati;
- value object e tipi di dominio iniziali implementati;
- aggregato ordine, righe, transizioni ed eventi di dominio implementati e coperti da test;
- elenco e dettaglio clienti, elenco e dettaglio ordini implementati come presentazione server-rendered;
- repository PostgreSQL dell’aggregato ordine, optimistic locking e scrittura outbox atomica implementati;
- repository cliente, query di elenco, casi d’uso, autenticazione, autorizzazione e CRUD non ancora implementati;
- e-commerce B2C completo pianificato.

## Cliente canonico

La tabella `customers` identifica il cliente con un `customer_code` interno stabile. Conserva:

- stato `active`, `inactive` o `archived`;
- tipo `person` o `business`;
- nome visualizzato e dati anagrafici disponibili;
- ragione sociale e identificativi fiscali opzionali;
- email originale e valore normalizzato per la ricerca;
- telefono e locale di presentazione;
- timestamp di creazione e aggiornamento.

L’email non è univoca. Account familiari, indirizzi condivisi, alias e dati mascherati dei marketplace rendono insicura una fusione automatica basata soltanto su questo campo. Ogni deduplicazione futura richiederà regole esplicite, evidenze multiple, autorizzazione e audit.

## Identità esterne

`customer_external_identities` collega il cliente canonico a una tripla univoca:

```text
sorgente + account_reference + external_customer_id
```

Le sorgenti iniziali sono Amazon, eMAG, Temu, IBS e il futuro `b2c_ecommerce`. SellRapido non è una sorgente cliente: è un connettore tecnico che trasporta identità appartenenti ai canali effettivi. Questa distinzione segue la stessa regola già applicata agli ordini.

La cancellazione del cliente rimuove le identità collegate. L’eventuale merge di clienti sarà un caso d’uso dedicato, transazionale e auditato; non viene effettuato da trigger o vincoli impliciti.

## Indirizzi e snapshot storici

`customer_addresses` conserva una rubrica modificabile con:

- etichetta e destinatario;
- righe indirizzo, codice postale, città, provincia e paese ISO;
- telefono opzionale;
- stato attivo;
- un solo indirizzo predefinito di spedizione e uno di fatturazione per cliente.

Gli indirizzi presenti sugli ordini sono invece snapshot storici `JSONB`. Modificare la rubrica del cliente non riscrive un ordine già acquisito. Gli snapshot di spedizione e fatturazione possono quindi rispettare obblighi operativi e documentali distinti dal profilo corrente.

## Anagrafica ordine

Ogni ordine dispone di:

- numero interno canonico e univoco;
- identificativo esterno;
- collegamento cliente opzionale;
- origine `marketplace` oppure `b2c_ecommerce`;
- riferimento dell’origine B2C, destinato a identificare lo storefront;
- data di inserimento ordine;
- snapshot distinti di spedizione e fatturazione;
- stato, valuta, versione e timestamp già previsti dal dominio operativo.

I vincoli PostgreSQL impediscono stati ambigui:

| Origine | `marketplace_id` | `origin_reference` |
|---|---:|---:|
| `marketplace` | obbligatorio | assente |
| `b2c_ecommerce` | assente | obbligatorio |

L’identificativo ordine esterno resta univoco per marketplace; per il futuro B2C è univoco all’interno dello storefront. Se il cliente viene cancellato o anonimizzato, l’ordine storico resta presente e il collegamento diventa nullo.

### Ciclo di dominio dell’ordine

`Order` è l’aggregate root e modifica le righe soltanto tramite operazioni controllate. Il modello implementato garantisce:

- numeri riga positivi e univoci nell’ordine;
- quantità ordinate, disponibili, da spedire e da annullare coerenti;
- aggiornamenti di disponibilità completi e atomici su tutte le righe;
- decisione esplicita prima di confermare un fulfilment parziale;
- indirizzo di spedizione obbligatorio prima dell’invio a Space;
- transizioni dichiarate, stati terminali e ritorno dalla revisione manuale al solo stato precedente;
- versione incrementale, controllo della versione attesa ed eventi di dominio rilasciabili;
- storico PostgreSQL con una sola transizione per versione ordine.

`PostgresOrderRepository` ricostituisce aggregato, righe, indirizzi e transizioni. Il salvataggio usa un controllo versione atomico durante l’`UPDATE`; ordine, righe, nuove transizioni ed eventi outbox vengono confermati o annullati nello stesso transaction boundary. Gli eventi restano nell’aggregato in caso di rollback e vengono rimossi soltanto dopo il commit. Il `Clock` condiviso è disponibile nel container e verrà usato dai casi d’uso che orchestrano le decisioni di dominio.

## Confine del futuro e-commerce B2C

La sola origine `b2c_ecommerce` è oggi una capacità di compatibilità del modello, non un e-commerce operativo. Restano esplicitamente da progettare e implementare:

- account cliente, verifica email, consensi e recupero accesso;
- varianti, contenuti, listini B2C, promozioni e imposte;
- esposizione nello storefront del catalogo operativo, della disponibilità vendibile e delle regole di vendita già predisposte;
- carrello, checkout, spedizione e calcolo dei totali;
- pagamento, SCA, webhook, rimborsi e riconciliazione finanziaria;
- conferme, notifiche e area personale;
- resi, cancellazioni e assistenza;
- privacy web, cookie e gestione dei diritti dell’interessato;
- sicurezza, antifrode, rate limiting e test end-to-end.

La foundation catalogo, prezzi Space, scorta di sicurezza e ricarichi marketplace è descritta in [`CATALOG_PRICING.md`](CATALOG_PRICING.md), ma non sostituisce le capacità e-commerce sopra elencate. Nessuna UI amministrativa o route pubblica attuale accetta ordini B2C.

## Sicurezza e ciclo di vita

Prima di collegare dati reali sono obbligatori:

1. autorizzazione distinta per consultazione, modifica, export, merge e anonimizzazione;
2. minimizzazione dei view model e paginazione server-side;
3. audit delle consultazioni e delle modifiche sensibili;
4. retention differenziata per profilo, indirizzi, snapshot ordine, audit e backup;
5. procedure verificate per accesso, rettifica, portabilità, cancellazione o anonimizzazione;
6. redazione di email, telefoni, indirizzi e identificativi fiscali da log e diagnostica;
7. cifratura e controllo degli accessi ai backup.

La roadmap esecutiva è in [`TODO.md`](TODO.md); i controlli completi sono in [`SECURITY.md`](SECURITY.md).
