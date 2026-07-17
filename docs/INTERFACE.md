# Interfaccia operativa HAPA

Ultimo riesame: 16 luglio 2026.

## Scopo

L’interfaccia HAPA governa anagrafiche, catalogo, regole commerciali e operazioni che richiedono un utente o una decisione di dominio.

Non è la console tecnica di `hapa-automation` e non espone scheduler, worker, retry provider o dead letter.

## Stato

Implementato come presentazione:

- layout applicativo e autenticazione;
- navigazione responsive;
- design system e componenti accessibili;
- escaping centralizzato e header di sicurezza;
- route GET delle aree HAPA;
- stati vuoti espliciti;
- pagina catalogo;
- pagina integrazioni con riferimento al servizio esterno.

Ancora da collegare:

- autenticazione e autorizzazione reali;
- repository e read model;
- azioni mutative;
- CSRF;
- audit delle operazioni UI.

## Mappa schermate

| Route | Area | Contenuto |
|---|---|---|
| `/login` | accesso | autenticazione |
| `/password/recovery` | accesso | recupero credenziali |
| `/ui` | dashboard | stato HAPA e integrazioni |
| `/ui/customers` | clienti | elenco e ricerca |
| `/ui/customers/{id}` | clienti | dettaglio cliente |
| `/ui/orders` | ordini | elenco e ricerca |
| `/ui/orders/{id}` | ordini | dettaglio ordine |
| `/ui/catalog` | prodotti | anagrafica, prezzo e stock Space, ricarichi e offerte |
| `/ui/picking` | magazzino | sessioni e anomalie |
| `/ui/shipments` | spedizioni | colli, label e tracking |
| `/ui/integrations` | configurazione | account, canali, provider e servizio `hapa-automation` |
| `/ui/audit` | controllo | eventi applicativi e di sicurezza |
| `/ui/users` | amministrazione | utenti e ruoli |
| `/ui/settings` | amministrazione | preferenze HAPA |
| `/ui/profile` | account | profilo e sicurezza personale |

La route `/ui/automation` è stata rimossa.

## Catalogo

La pagina catalogo deve rappresentare il prodotto HAPA, non un job tecnico.

Deve mostrare:

- SKU e identificativi commerciali;
- identificativo Space;
- costo di acquisto e disponibilità Space;
- stock Space;
- versione e data di sincronizzazione;
- regola di ricarico applicata;
- prezzo finale;
- stato delle offerte per marketplace;
- divergenze e dati scaduti.

Creazione, modifica, abilitazione/disabilitazione e ritiro delle regole di ricarico sono disponibili agli amministratori con permesso, CSRF, optimistic locking, storico versionato e audit. Anteprima prezzo e pubblicazione offerte restano successive.

## Integrazioni

La pagina integrazioni mostra la configurazione applicativa e il confine operativo:

- Space è sorgente di prezzo e stock e destinazione degli ordini;
- marketplace e SellRapido sono canali/connettori;
- GLS e BRT sono provider Shipping;
- `hapa-automation` è il runtime separato con RabbitMQ e database proprio.

La pagina non deve simulare lo stato delle code. Eventuali metriche devono arrivare da un contratto di osservabilità esplicito e in sola lettura.

## Navigazione

La navigazione è organizzata in:

1. **Operatività**: dashboard, clienti, ordini, catalogo, picking e spedizioni;
2. **Controllo**: integrazioni e audit;
3. **Amministrazione**: utenti, impostazioni e profilo.

## Sicurezza

- nessuna azione mutativa prima di autenticazione e CSRF;
- permessi valutati server-side;
- dati tecnici e segreti non esposti;
- messaggi d’errore senza payload provider sensibili;
- audit delle modifiche prodotto e ricarico;
- stato del servizio esterno mostrato solo tramite dati autorizzati e redatti.
