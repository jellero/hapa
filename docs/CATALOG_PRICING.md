# Anagrafica prodotti, prezzi, stock e ricarichi

Ultimo riesame: 17 luglio 2026.

## Scopo

HAPA possiede un’anagrafica prodotti. Space è un fornitore: costo di acquisto, identificativo e disponibilità Space sono una distinta offerta fornitore collegata al prodotto, non attributi che definiscono l’identità del prodotto HAPA.

La formulazione corretta non è «HAPA applica una scorta di sicurezza e delle regole di ricarico» come attività isolata. Il flusso è:

1. Space fornisce a HAPA anagrafica, prezzo e stock;
2. HAPA conserva identità prodotto e offerta Space in strutture separate;
3. l’operatore gestisce dall’interfaccia le regole di ricarico;
4. HAPA calcola e versiona il prezzo finale desiderato;
5. `hapa-automation` pubblica prezzo e quantità sui marketplace e restituisce l’esito.

## Ownership

| Dato | Sorgente autorevole | Responsabilità |
|---|---|---|
| SKU canonico HAPA | HAPA | identifica il prodotto nei casi d’uso interni |
| identificativo prodotto Space | Space | viene mappato al prodotto HAPA |
| costo di acquisto Space | Space | viene sincronizzato nell’offerta fornitore e mai sovrascritto dai marketplace |
| disponibilità Space | Space | viene sincronizzata e versionata nell’offerta fornitore |
| regole di ricarico | HAPA | sono gestite da interfaccia, autorizzate e auditate |
| prezzo finale desiderato | HAPA | deriva dal prezzo Space e dalla regola applicabile |
| stato di pubblicazione | HAPA | descrive l’ultima intenzione e l’ultimo esito noto |
| identificativo/versione remota | marketplace | serve alla riconciliazione |
| cursori e retry provider | `hapa-automation` | appartengono al database del servizio asincrono |

Una eventuale politica di quantità pubblicabile o riserva di stock è una regola commerciale distinta e opzionale. Non deve sostituire il dato stock sincronizzato da Space né essere descritta come il centro dell’anagrafica prodotto.

## Modello prodotto

L’anagrafica prodotto e la collegata offerta fornitore devono esporre almeno:

- SKU HAPA;
- EAN o altri codici commerciali opzionali;
- identificativo Space;
- descrizione e stato del prodotto;
- valuta;
- costo di acquisto Space in unità minori;
- disponibilità Space;
- versione sorgente Space;
- data dell’ultima sincronizzazione;
- stato di validità del dato;
- versione HAPA del prezzo commerciale.

Costo e disponibilità vengono aggiornati soltanto da messaggi Space validi e più recenti della versione già applicata. Gli aggiornamenti fuori ordine vengono ignorati o inviati a riconciliazione.

## Regole di ricarico

Le regole sono configurate dall’interfaccia HAPA e possono avere ambito:

- globale;
- marketplace;
- SKU;
- marketplace + SKU.

Tipi supportati:

- percentuale;
- importo fisso;
- prezzo finale fisso.

Una regola dichiara codice, nome, ambito, priorità, valuta, valore, finestra di validità, stato e limiti opzionali. Le modifiche richiedono autenticazione, autorizzazione, CSRF, optimistic locking e audit.

HAPA seleziona una sola regola vincente. La precedenza è:

1. marketplace + SKU;
2. SKU;
3. marketplace;
4. globale.

A parità di ambito vince la priorità numerica maggiore; il codice regola risolve in modo deterministico un ulteriore pareggio.

## Flusso di sincronizzazione

### Space verso HAPA

1. `hapa-automation` interroga Space usando un cursore persistito nel proprio database.
2. Il servizio valida e pubblica su RabbitMQ `space.catalog.item.observed`.
3. HAPA deduplica il messaggio e aggiorna prodotto e offerta fornitore nella propria transazione.
4. HAPA registra l’evento applicativo derivato nella transactional outbox.
5. Il cursore Space avanza nel servizio asincrono soltanto dopo l’esito previsto dal contratto.

### HAPA verso marketplace

1. un aggiornamento del prodotto o di una regola modifica il prezzo finale desiderato;
2. HAPA versiona l’offerta e produce un comando con prezzo e quantità già calcolati;
3. `hapa-automation` consuma l’evento e chiama il solo connettore attivo per account-canale;
4. il servizio pubblica su RabbitMQ esito, versione remota ed eventuale errore;
5. HAPA aggiorna lo stato dell’offerta senza cambiare costo o disponibilità Space.

## Interfaccia

La pagina `/ui/catalog` deve consentire, dopo i gate di sicurezza:

- ricerca e consultazione dell’anagrafica prodotti;
- visualizzazione di prezzo e stock Space;
- indicazione della data e versione di sincronizzazione;
- gestione delle regole di ricarico;
- anteprima del prezzo finale;
- consultazione dello stato delle offerte per marketplace;
- evidenza di dati scaduti o divergenze;
- audit delle modifiche.

La UI HAPA non espone scheduler, retry o dead letter: tali funzioni appartengono a `hapa-automation`.

## Failure mode

| Caso | Comportamento |
|---|---|
| evento duplicato | nessun doppio aggiornamento |
| versione Space precedente | ignorare e registrare la divergenza |
| prezzo o stock non validi | non aggiornare il prodotto e richiedere verifica |
| dato Space scaduto | mostrare lo stato e applicare una policy commerciale esplicita |
| regola incoerente | mantenere l’ultima configurazione valida |
| timeout marketplace dopo invio | riconciliare prima del retry |
| errore definitivo provider | stato errore e revisione autorizzata |

## Gate

Prima dell’esercizio reale servono:

1. repository e casi d’uso prodotto;
2. consumer RabbitMQ HAPA idempotente;
3. API o adapter Space verificati nel servizio `hapa-automation`;
4. autenticazione, autorizzazione, CSRF e audit della gestione ricarichi;
5. test di versionamento e messaggi fuori ordine;
6. test di calcolo prezzi e rounding;
7. riconciliazione marketplace;
8. metriche sull’età del dato Space e sulle pubblicazioni;
9. pilot su un solo account-canale.

## Futuro B2C

L’anagrafica prodotto e il motore di ricarico possono essere riutilizzati dal futuro e-commerce, ma non implementano varianti, contenuti, imposte, promozioni, checkout, pagamenti, resi o area cliente.
