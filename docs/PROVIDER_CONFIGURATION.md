# Configurazione provider da interfaccia

Ultimo riesame: 17 luglio 2026.

## Obiettivo

Gli account tecnici usati da HAPA Automation per Space, SellRapido, GLS e futuri provider devono essere configurabili da un'interfaccia amministrativa HAPA. Nessun cambio di endpoint, account, catalogo, contratto, capacità o credenziale deve richiedere una modifica al codice o una nuova immagine Docker.

HAPA conserva la configurazione applicativa e lo stato desiderato. HAPA Automation conserva i segreti, i token e lo stato tecnico delle sessioni provider.

## Confine dei dati

| Dato | Proprietario | Visibilità UI |
|---|---|---|
| nome account, provider, ambiente e descrizione | HAPA | leggibile e modificabile |
| capacità abilitate | HAPA | leggibile e modificabile |
| account marketplace e canale downstream | HAPA | leggibile e modificabile |
| cataloghi, codici contratto, formati e policy non segrete | HAPA | leggibile e modificabile |
| username, password, client secret e chiavi API | HAPA Automation | solo scrittura, sempre mascherati |
| access token e refresh token | HAPA Automation | mai mostrati e mai modificati manualmente |
| scadenze token, ultimo test e stato connessione | HAPA Automation | leggibile senza valore segreto |
| retry, checkpoint, rate limit e operazioni provider | HAPA Automation | leggibile come diagnostica autorizzata |

HAPA non salva una copia delle credenziali. Il backend HAPA inoltra i nuovi valori a un'API amministrativa interna di Automation autenticata e cifrata; i valori non transitano su RabbitMQ, nei log o nelle risposte successive.

## Modello applicativo target

La configurazione deve distinguere:

- `integration_accounts`: istanza di un provider, ambiente, nome visualizzato e stato desiderato;
- `integration_account_capabilities`: capacità abilitate per account, ad esempio `products.read`, `products.write`, `orders.read`, `orders.status.write`, `shipping.create`, `shipping.close` e `labels.read`;
- `integration_account_settings`: impostazioni non segrete validate per schema e versionate;
- `integration_secret_status`: riferimento opaco, stato, versione, ultima rotazione e ultima verifica; non contiene il segreto;
- `marketplace_accounts`: account commerciale e canale downstream, collegato all'account tecnico che lo serve.

Ogni modifica incrementa una versione configurazione. Automation applica soltanto versioni monotone e restituisce l'esito del test o dell'attivazione.

## Interfaccia amministrativa

Per ciascun account devono essere disponibili:

1. creazione e modifica dei dati non segreti;
2. inserimento o sostituzione dei segreti tramite campi write-only;
3. test connessione senza attivare job o comandi reali;
4. rotazione o revoca delle credenziali;
5. abilitazione e disabilitazione per singola capacità;
6. configurazione di frequenza, finestra di sovrapposizione, batch e limiti entro valori sicuri;
7. visualizzazione di ultimo test, ultimo successo, ultimo errore redatto, scadenza token e stato checkpoint;
8. audit di chi ha modificato, testato, ruotato, abilitato o disabilitato l'account.

I segreti esistenti non vengono mai restituiti al browser. Un campo vuoto significa "non modificare"; la revoca è un'azione esplicita e separata.

### Implementazione operativa

La UI espone campi specifici per Space, SellRapido, GLS, BRT, Amazon e Temu. Le route di sostituzione e revoca richiedono sessione amministrativa, permesso dedicato e token CSRF. Il backend invia il payload direttamente a `hapa-automation` tramite HTTPS in produzione e bearer token letto da secret file. Automation valida i nomi campo con una allowlist per provider, cifra l'oggetto con `sodium_crypto_secretbox` e conserva nonce e ciphertext nel proprio PostgreSQL.

Ogni sostituzione incrementa `secret_version`; una sostituzione parziale mantiene gli altri campi già cifrati. Lo storico append-only registra soltanto provider, account, versione, azione e nomi dei campi, mai i valori. La revoca incrementa ancora la versione e imposta a `NULL` nonce e ciphertext. La chiave `PROVIDER_SECRET_KEY` e il token `AUTOMATION_ADMIN_API_TOKEN` devono risiedere fuori dal database e usare secret file o secret manager in produzione.

La configurazione non segreta viene applicata separatamente con una versione monotona. Automation rifiuta versioni regressive e conflitti con fingerprint diverso sulla stessa versione, conserva una proiezione tecnica e uno storico append-only, e ripete in difesa la scansione delle chiavi sensibili. La UI mostra la versione HAPA e quella Automation e non consente pilot o attivazione finché credenziali, test connessione e sincronizzazione non sono tutti validi.

Per SellRapido la UI esegue un test reale di autenticazione e lettura ordini, mostra la scadenza redatta del token e consente un import immediato sugli account pilot o attivi. La sincronizzazione di una configurazione pilot/attiva abilita il polling automatico in Automation; disabilitazione, sospensione o ritiro lo arrestano quando non restano altri account eleggibili.

## SellRapido

Impostazioni non segrete minime:

- URL base, predefinito `https://app.sellrapido.com/sr_company_ws` ma modificabile per ambiente;
- account e descrizione;
- catalogo SellRapido e UUID richiesto dagli endpoint di scrittura, da verificare sul contratto reale;
- marketplace e canale downstream, inizialmente IBS;
- stati ordine da importare;
- frequenza polling, overlap del watermark, dimensione pagina e dimensione batch;
- modalità catalogo e policy `fields_lock`;
- mapping corrieri e stato fulfilment;
- capacità `products.read`, `products.write`, `orders.read` e `orders.status.write`.

Segreti write-only:

- username API;
- password API.

Access token, refresh token e relative scadenze sono gestiti soltanto da Automation. L'interfaccia espone lo stato della sessione, non i token.

## GLS

Impostazioni non segrete minime:

- endpoint e WSDL per ambiente;
- `SedeGls`, `CodiceClienteGls` e `CodiceContrattoGls`;
- regola di aggregazione concordata;
- formato label PDF/ZPL;
- strategia di chiusura e capacità abilitate;
- timeout, retry e ambiente di collaudo/produzione.

Segreto write-only:

- `PasswordClienteGls`.

La modifica di contratto, sede o regola di aggregazione richiede un nuovo test e non altera spedizioni già aperte.

## Space

Impostazioni non segrete minime:

- URL dell'API PHP Space e ambiente;
- path health check e path creazione acquisti;
- nome dell'header per la chiave applicativa, timeout e limite risposta;
- timeout, polling e overlap;
- mapping versione degli stati;
- capacità catalogo, creazione acquisto e lettura stato.

I segreti dell'API Space sono write-only e rimangono in Automation.
Il test connessione Space è disponibile dalla stessa scheda account e usa l'endpoint salute configurato. Solo un account `pilot` o `active`, sincronizzato, con segreti configurati, test superato e capacità `purchase_orders.write` può ricevere acquisti automatici. Al completamento del test o della sincronizzazione HAPA recupera gli ordini marketplace preesistenti ancora senza acquisto o in `manual_review`.

## Sicurezza e audit

- autorizzazione deny-by-default e ruoli distinti per visualizzare, modificare, testare e attivare;
- conferma esplicita per cambio ambiente, revoca e attivazione produzione;
- cifratura at rest tramite secret manager o chiave esterna al database;
- redazione centralizzata di username, password, token, XML e payload sensibili;
- nessun segreto in URL, RabbitMQ, metriche, eccezioni o `provider_operations`;
- storico append-only delle modifiche non segrete e degli eventi di rotazione;
- alert prima della scadenza dei token o in caso di autenticazione/rate limit ripetuti.

## Gate

Un account non può essere abilitato finché configurazione, segreti, permessi, test connessione, rate limit, metriche e riconciliazione non risultano validi. La UI non può aggirare i gate tecnici di Automation.
