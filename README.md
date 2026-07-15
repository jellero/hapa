# HAPA

## Relazione sintetica del progetto

Il progetto prevede la realizzazione di una piattaforma per la gestione completa degli ordini provenienti da marketplace generici, dalla presa in carico fino alla comunicazione della spedizione.

Il flusso operativo comprende:

1. accettazione dell’ordine sul marketplace;
2. recupero dei dati di spedizione;
3. importazione dell’ordine e delle relative righe;
4. invio degli articoli a Space tramite API;
5. verifica della disponibilità della merce;
6. preparazione dell’ordine tramite picking barcode;
7. gestione degli ordini completi e parziali;
8. creazione della spedizione e dell’etichetta GLS;
9. invio del tracking al marketplace;
10. controllo dell’intero processo tramite pannello operativo.

Il marketplace viene trattato come canale generico attraverso adapter dedicati. Questo consente di integrare più canali mantenendo invariato il processo interno di gestione dell’ordine.

Space rappresenta il sistema di approvvigionamento e disponibilità della merce. La comunicazione avviene tramite API, con registrazione delle richieste, delle risposte e degli eventuali errori. Il magazzino riceve quindi ordini già classificati come completi, incompleti o parzialmente disponibili.

La fase di picking utilizza la lettura barcode per verificare articoli, quantità e anomalie. Gli ordini parziali prevedono una conferma operativa delle quantità da spedire e da annullare. Una volta completata la preparazione, il sistema genera la spedizione GLS, rende disponibile l’etichetta e trasmette il tracking al marketplace di origine.

## Architettura applicativa

La piattaforma sarà sviluppata su un **framework custom proprietario** in PHP 8.4, costruito per applicazioni gestionali modulari e transazionali.

La foundation comprende già:

- kernel applicativo e routing;
- dependency injection;
- autenticazione e autorizzazione;
- utenti, ruoli e permessi;
- gestione delle transazioni;
- logging e audit;
- migrazioni database;
- configurazione degli ambienti;
- pipeline di test e analisi statica;
- procedure Docker e deploy.

Lo stack tecnologico utilizza PHP 8.4, PostgreSQL, Redis, Nginx, PHP-FPM, Docker, Phinx, PHPUnit, PHPStan e componenti Symfony selezionati.

Il Core contiene i servizi trasversali, mentre il dominio viene suddiviso in moduli applicativi dedicati:

```text
Marketplace
Orders
Space
Warehouse
Picking
PartialOrders
GLS
Tracking
Automation
OperationalDashboard
```

Ogni modulo espone contratti espliciti e concentra controller, servizi, repository, validatori e policy della propria area. L’accesso ai dati utilizza repository e SQL controllato, con transazioni governate dal service layer.

## Affidabilità delle integrazioni

Le comunicazioni con marketplace, Space e GLS vengono gestite tramite adapter tipizzati e idempotenti.

Il sistema utilizza:

- transactional outbox;
- job durevoli;
- scheduler;
- worker concorrenti;
- API log;
- retry degli errori temporanei;
- classificazione degli errori definitivi;
- audit delle operazioni manuali;
- correlation ID per la tracciabilità end-to-end.

I job acquisiscono il lavoro tramite lock e claim atomici PostgreSQL, garantendo elaborazioni concorrenti controllate e protezione dalle duplicazioni.

Lo stato di business dell’ordine rimane nel gestionale, mentre scheduler, tentativi, consegne esterne ed esiti tecnici vengono modellati separatamente. Questa distinzione semplifica retry, riconciliazione e analisi degli errori.

## Velocità di sviluppo e maturità

L’utilizzo del framework proprietario consente di concentrare lo sviluppo direttamente sulle vertical slice specifiche del progetto.

Autenticazione, sicurezza, transazioni, logging, migrazioni, test, configurazione e deploy costituiscono componenti già maturati e riutilizzabili. L’effort viene quindi focalizzato sui moduli di business e sulle integrazioni Space e GLS.

La qualità viene verificata tramite test unitari, di integrazione, funzionali, di concorrenza e architetturali, affiancati da analisi statica e controlli automatici sulle dipendenze tra moduli.

Questo approccio produce tre vantaggi principali:

- riduzione del time-to-market;
- maggiore prevedibilità dello sviluppo;
- riutilizzo di componenti proprietari già verificati.

## Scalabilità

L’architettura permette di scalare separatamente:

- processi HTTP;
- worker;
- scheduler;
- Redis;
- PostgreSQL;
- storage delle etichette;
- processi di integrazione.

L’aumento del carico viene gestito inizialmente tramite replica dei worker e concorrenza controllata. Le aree con volumi o requisiti operativi specifici possono successivamente evolvere in servizi dedicati, mantenendo stabili i contratti applicativi e la proprietà del dominio.

Il risultato è una piattaforma con base tecnologica matura, sviluppo rapido, controllo esplicito dei processi e un percorso di crescita progressivo.
