# Allineamento architetturale con Symfony

Ultimo riesame: 16 luglio 2026.

## 1. Scopo

Questo documento confronta la foundation HAPA con le pratiche e i componenti Symfony attuali. È un allegato normativo di [`ARCHITECTURE.md`](ARCHITECTURE.md): definisce quali principi vengono adottati, quali componenti Symfony risultano utili e quali responsabilità restano nel framework custom proprietario.

HAPA usa Symfony come raccolta di primitive infrastrutturali, non come vincolo sul dominio o come sostituzione della propria architettura applicativa.

## 2. Valutazione sintetica

La foundation corrente risulta già allineata sui punti principali:

- front controller e ciclo HTTP esplicito;
- dependency injection tramite costruttore come direzione architetturale;
- configurazione infrastrutturale tramite ambiente e secret esterni;
- servizi di dominio separati dal delivery HTTP;
- error handling centralizzato;
- correlation ID, logging strutturato e health check;
- PostgreSQL come sorgente autorevole;
- messaggistica asincrona progettata per consegna almeno una volta;
- test unitari, integration e smoke production;
- componenti Symfony selezionati al posto dell’adozione indiscriminata del framework completo.

Le integrazioni necessarie riguardano soprattutto composition root, sicurezza del pannello, client HTTP, ciclo di vita dei worker e scheduler.

## 3. Decisione sul framework

### 3.1 Componenti da adottare

La direzione consigliata comprende:

- `symfony/dependency-injection` per il container compilato;
- `symfony/config` soltanto dove serve validazione strutturata di configurazioni complesse;
- `symfony/http-client` per gli adapter Marketplace, Space e GLS;
- `symfony/validator` per validazione ai confini HTTP e provider;
- `symfony/serializer` quando riduce il mapping ripetitivo, mantenendo DTO espliciti e mapping controllato;
- `symfony/clock` per tempo deterministico in dominio, retry, lock e scheduler;
- `symfony/password-hasher`, `symfony/security-csrf` e componenti Security necessari al pannello operativo;
- `symfony/rate-limiter` per login, azioni operative e quote applicative;
- `symfony/lock` per sezioni critiche distribuite e prevenzione della sovrapposizione dei job.

### 3.2 Componenti da valutare in seguito

- Messenger può fornire primitive di worker, handler e lifecycle, ma la transactional outbox PostgreSQL resta la sorgente durevole delle intenzioni applicative.
- Scheduler può fornire trigger e diagnostica, purché l’emissione dei job resti protetta da lock distribuito e cursori persistenti.
- Cache può essere adottata per pool e stampede protection, mantenendo Redis come supporto e PostgreSQL come sorgente autorevole.

### 3.3 Componenti esclusi come requisito architetturale

HAPA non richiede:

- FrameworkBundle;
- Doctrine ORM;
- bundle applicativi per organizzare il dominio;
- un secondo sistema di coda che replichi la transactional outbox;
- controller contenenti logica di business;
- service locator o accesso globale al container.

## 4. Dependency injection e composition root

Symfony raccomanda autowiring, autoconfiguration, servizi privati e constructor injection. HAPA adotta lo stesso principio con una configurazione PHP coerente con il framework custom proprietario.

### 4.1 Container compilato

Il composition root deve costruire un `ContainerBuilder`, registrare servizi e alias, applicare compiler pass e compilare il grafo prima dell’avvio production.

Requisiti:

- servizi privati per impostazione predefinita;
- entry point, controller factory e console come servizi pubblici strettamente necessari;
- constructor injection per ogni dipendenza;
- alias espliciti tra interfacce e implementazioni;
- named alias per client o adapter multipli;
- tagged iterator per handler outbox, adapter marketplace, policy e subscriber;
- validazione del container in CI;
- cache del container compilato associata al commit distribuito;
- ricostruzione del container a ogni deploy.

### 4.2 Confini del container

Il container appartiene alla composizione infrastrutturale. Entità, value object e regole di dominio restano normali oggetti PHP e non ricevono il container.

Il codice applicativo non usa `$container->get()` e non dipende da un service locator.

### 4.3 Configurazione

La distinzione adottata è:

- variabili ambiente e secret per valori infrastrutturali che cambiano per installazione;
- oggetti di configurazione tipizzati per comportamento applicativo;
- costanti PHP per invarianti e valori raramente modificabili;
- feature flag espliciti e validati per variazioni operative controllate.

Il composition root rimane l’unico punto autorizzato a leggere ambiente e secret.

## 5. Validazione, serializzazione e mapping

### 5.1 Validazione ai confini

Symfony Validator viene usato per:

- request HTTP;
- comandi applicativi;
- payload ricevuti dai provider;
- configurazioni operative;
- form del pannello.

Le invarianti del dominio restano implementate nel dominio. La validazione del boundary migliora il messaggio di errore, mentre il dominio conserva l’integrità finale.

### 5.2 Mapping provider

Ogni adapter applica la sequenza:

1. ricezione del payload;
2. verifica di status, content type e dimensione;
3. decoding controllato;
4. validazione schema e campi obbligatori;
5. mapping verso DTO interni;
6. costruzione del comando o risultato applicativo;
7. redazione del payload tecnico prima di log o persistenza.

Campi sconosciuti vengono gestiti con una policy esplicita. Campi critici mancanti o incompatibili producono un errore definitivo di mapping, non un oggetto parziale.

### 5.3 Versionamento

Messaggi outbox, payload persistiti e DTO destinati a sopravvivere a un deploy includono una versione di schema. I worker devono poter distinguere messaggi correnti, migrabili e incompatibili.

## 6. Client HTTP verso provider

Symfony HttpClient offre scoped client, timeout complessivi, retry configurabile e client mockabili. HAPA adotta un client dedicato per provider.

Ogni client Marketplace, Space o GLS definisce:

- base URI fissa e validata;
- credenziali iniettate tramite secret;
- TLS verification attiva;
- timeout di connessione;
- timeout di inattività;
- durata massima dell’intera operazione;
- limite di redirect impostato a zero o a un valore minimo motivato;
- limite alla dimensione della risposta;
- user agent applicativo e correlation ID;
- metriche per latenza, status e tentativi;
- redazione di header e parametri sensibili.

### 6.1 Protezione SSRF

Gli URL completi provenienti da payload esterni non vengono inoltrati direttamente al client.

Quando un’integrazione richiede URL dinamici:

- schema e host appartengono a una allowlist;
- reti private, loopback, link-local e metadata endpoint vengono bloccati;
- redirect verso host differenti vengono rifiutati;
- eventuali host privati autorizzati sono dichiarati esplicitamente;
- risoluzione DNS e destinazione effettiva restano sottoposte alla policy di rete.

### 6.2 Retry HTTP

Il retry automatico viene applicato soltanto a errori temporanei e operazioni idempotenti.

Regole:

- GET e letture possono essere ritentate secondo policy;
- POST mutative richiedono idempotency key provider o una riconciliazione sicura;
- errori di validazione e autenticazione terminano il tentativo;
- `Retry-After` viene rispettato;
- backoff e jitter appartengono alla policy applicativa;
- il numero totale di tentativi comprende retry del client e retry outbox, così da evitare moltiplicazioni incontrollate.

## 7. Sicurezza del pannello operativo

### 7.1 Contesto di autenticazione

Il pannello usa un contesto di sicurezza principale basato su sessione. Un eventuale accesso macchina tramite token appartiene a un contesto distinto soltanto quando esiste un requisito reale.

### 7.2 Password

- password hashing tramite algoritmo `auto` o equivalente aggiornabile;
- rehash trasparente al login quando i parametri diventano obsoleti;
- password mai registrate o persistite in chiaro;
- token di reset casuali, monouso, con scadenza e valore hashato a riposo;
- invalidazione delle sessioni dopo reset password o modifica dei privilegi;
- MFA richiesta per ruoli amministrativi e operazioni ad alto impatto, quando il pannello entra in esercizio reale.

### 7.3 Sessione

- cookie `Secure`, `HttpOnly` e `SameSite` coerente con il flusso;
- rotazione dell’identificativo dopo autenticazione e variazione dei privilegi;
- scadenza per inattività e durata massima assoluta;
- logout server-side e revoca della sessione;
- reautenticazione per gestione utenti, secret, ristampa massiva, annullamenti e replay di dead letter;
- protezione da session fixation e session hijacking;
- sessioni attive consultabili e revocabili dagli amministratori autorizzati.

### 7.4 CSRF e login throttling

La protezione CSRF copre login e ogni operazione mutativa basata su cookie.

Il login throttling combina almeno:

- account normalizzato;
- indirizzo IP o rete sorgente;
- finestra temporale;
- risposta generica che non rivela l’esistenza dell’utente.

Il rate limiting applicativo protegge login e azioni costose; la protezione da saturazione volumetrica appartiene al reverse proxy o alla frontiera di rete.

### 7.5 Autorizzazione

L’autorizzazione segue deny-by-default:

- controllo di accesso per area e route;
- policy o voter per risorsa e azione;
- permessi valutati server-side;
- separazione tra lettura, modifica, approvazione, annullamento, retry e amministrazione;
- verifica della versione dell’ordine prima di azioni concorrenti;
- audit di ogni azione concessa ad alto impatto e dei dinieghi rilevanti.

## 8. Transactional outbox e confronto con Messenger

Messenger assume che un messaggio possa essere consegnato più volte. HAPA adotta la stessa semantica e rende gli handler idempotenti.

### 8.1 Sorgente durevole

La tabella outbox PostgreSQL è la sorgente autorevole. Un evento di dominio e il record outbox vengono confermati nella stessa transazione.

Un eventuale transport Messenger può essere usato come meccanismo di esecuzione, mai come unica copia dell’intenzione applicativa.

### 8.2 Lifecycle del worker

Il worker deve supportare:

- identity univoca;
- claim atomico;
- lock con scadenza;
- heartbeat quando il task supera una soglia;
- timeout per singolo handler;
- limite di memoria e numero massimo di job per processo;
- reset dei servizi stateful tra job;
- chiusura graceful su `SIGTERM` e `SIGINT`;
- supervisor o orchestratore che garantisca il riavvio;
- readiness distinta da liveness;
- deploy con arresto e riavvio coordinato dei worker.

### 8.3 Retry e dead letter

- retry con limite massimo, exponential backoff e jitter;
- classificazione temporaneo, definitivo, autenticazione, rate limit e payload incompatibile;
- dead letter persistente;
- comandi operativi per ispezione, retry e rimozione autorizzata;
- replay idempotente e auditato;
- statistiche per tipo messaggio, provider, età e numero tentativi;
- gestione esplicita dei messaggi indecodificabili dopo variazioni di codice.

## 9. Scheduler e lock distribuiti

Lo scheduler produce intenzioni; i worker eseguono i casi d’uso.

Ogni job ricorrente definisce:

- frequenza;
- timezone;
- jitter;
- policy di sovrapposizione;
- lock distribuito;
- scadenza del lock;
- misfire policy dopo downtime;
- cursore persistente o watermark;
- intervallo massimo recuperabile;
- idempotency key per esecuzione logica;
- metriche su ultima esecuzione, prossima esecuzione e ritardo.

Import ordini, disponibilità, tracking e riconciliazione conservano cursori nel database. Il processo scheduler non usa la memoria come unica sorgente della pianificazione operativa.

Symfony Lock può proteggere il ruolo di scheduler leader e job globali. Le modifiche concorrenti a un singolo ordine restano protette da optimistic locking e transazioni PostgreSQL.

## 10. Rate limiting e quote provider

Esistono tre livelli distinti:

1. frontiera: protezione volumetrica e DoS sul reverse proxy;
2. applicazione: login, endpoint sensibili e azioni costose;
3. integrazione: quote Marketplace, Space e GLS.

Le quote provider usano bucket separati per account, operazione e credenziale. Lo stato distribuito può risiedere in Redis; il fallimento di Redis deve seguire una policy esplicita per ciascun limiter.

Il rate limiter non sostituisce la concorrenza controllata dei worker né il backpressure della coda.

## 11. Tempo deterministico

Il dominio e i servizi applicativi ricevono un’interfaccia Clock.

La lettura diretta di `time()`, `microtime()` o `new DateTimeImmutable()` resta confinata nell’implementazione del clock di sistema.

Questo rende deterministici:

- transizioni temporali;
- scadenze sessione e token;
- backoff;
- retry;
- lock;
- scheduler;
- test su timezone e cambio ora legale.

PostgreSQL conserva timestamp UTC; la timezone dell’operatore viene applicata nel delivery.

## 12. Eventi e side effect

EventDispatcher può essere usato per notifiche interne sincrone e disaccoppiamento infrastrutturale, rispettando queste regole:

- il dominio produce eventi espliciti;
- un listener sincrono non esegue chiamate provider irreversibili;
- gli effetti esterni attraversano outbox;
- l’ordine dei listener non rappresenta una regola di business implicita;
- gli errori dei listener seguono una policy dichiarata;
- gli eventi destinati alla persistenza hanno schema versionato.

## 13. Cache e Redis

Redis resta un acceleratore e un coordinatore.

Ogni cache definisce:

- namespace e versione;
- chiave deterministica;
- TTL;
- strategia di invalidazione;
- comportamento in caso di indisponibilità;
- protezione da cache stampede quando il carico lo richiede;
- assenza di dati personali eccedenti il minimo necessario.

Dati necessari alla ricostruzione dell’ordine restano in PostgreSQL.

## 14. Test allineati alle primitive Symfony

La strategia di test aggiunge:

- compilazione e validazione del container;
- test degli alias e dei tagged handler;
- MockHttpClient o equivalente per adapter;
- clock finto per retry, scadenze e scheduler;
- test della matrice ruoli × azioni;
- test CSRF, session rotation, login throttling e revoca;
- test SSRF, redirect, timeout e response size;
- test di messaggi duplicati e replay dead letter;
- test di graceful shutdown del worker;
- smoke test di tutte le route pubbliche con URL espliciti;
- test di compatibilità dei messaggi persistiti tra release consecutive.

## 15. Gate architetturali

Prima della prima integrazione reale:

- container compilato e validato;
- configurazioni tipizzate;
- Clock iniettato;
- scoped HTTP client con policy di rete e timeout;
- validazione dei payload provider;
- DTO completi e versionati dove persistiti.

Prima del pannello operativo:

- password hasher aggiornabile;
- session security completa;
- CSRF login e operazioni mutative;
- login throttling;
- autorizzazione deny-by-default con policy per azione;
- audit delle operazioni sensibili.

Prima dei worker production:

- idempotenza verificata;
- lifecycle e graceful shutdown;
- retry e dead letter;
- lock e recovery;
- supervisor e metriche;
- compatibilità dei messaggi persistiti.

## 16. Riferimenti Symfony ufficiali

- [Best practices](https://symfony.com/doc/current/best_practices.html)
- [Service container](https://symfony.com/doc/current/service_container.html)
- [Security](https://symfony.com/doc/current/security.html)
- [HTTP Client](https://symfony.com/doc/current/http_client.html)
- [Messenger](https://symfony.com/doc/current/messenger.html)
- [Scheduler](https://symfony.com/doc/current/scheduler.html)
- [Lock](https://symfony.com/doc/current/lock.html)
- [Rate Limiter](https://symfony.com/doc/current/rate_limiter.html)
- [Secrets](https://symfony.com/doc/current/configuration/secrets.html)
