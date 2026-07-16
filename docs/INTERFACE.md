# Interfaccia operativa HAPA

Ultimo riesame: 16 luglio 2026.

## 1. Scopo e maturità

L’interfaccia operativa fornisce il layer di presentazione per clienti, ordini, picking, spedizioni, automazioni, integrazioni, audit, utenti e configurazione.

Lo stato corrente è **parziale**:

- layout applicativo e autenticazione implementati;
- navigazione responsive e design system implementati;
- tutte le aree previste raggiungibili tramite route GET;
- escaping centralizzato, CSP e header di isolamento implementati;
- stati vuoti e indisponibilità espliciti, senza dati dimostrativi;
- autenticazione, autorizzazione, repository e azioni mutative non ancora collegati.

Le schermate di login e recupero accesso sono presentazionali e mantengono i form disabilitati. Il pannello non espone dati reali né operazioni mutative finché i gate di sicurezza della fase 8 non sono completati.

## 2. Architettura di presentazione

L’interfaccia è server-rendered e usa le primitive già presenti nel progetto:

```text
Request
  -> Kernel e routing Symfony
  -> UiController
  -> view model tipizzato progressivamente
  -> ViewRenderer
  -> template PHP con escaping centralizzato
  -> Response HTML no-store
```

Non viene introdotta una seconda applicazione SPA. CSS, JavaScript e sprite SVG sono serviti da `/public/assets` senza dipendenze da CDN.

Responsabilità:

- `UiController`: costruzione dei view model e selezione della vista;
- `ViewRenderer`: risoluzione confinata dei template, escaping e risposta HTML;
- `templates/layouts`: shell autenticazione e shell applicativa;
- `templates/ui`: pagine operative;
- `templates/auth`: accesso e recupero credenziali;
- `public/assets/ui.css`: token e componenti visivi;
- `public/assets/ui.js`: interazioni progressive non critiche;
- `public/assets/icons.svg`: icone vettoriali locali.

La logica di business non entra nei template. Controller, view model e template non eseguono query dirette e non chiamano provider esterni.

## 3. Mappa dell’interfaccia

| Route | Area | Stato corrente |
|---|---|---|
| `/login` | accesso operatore | presentazione, form disabilitato |
| `/password/recovery` | recupero credenziali | presentazione, form disabilitato |
| `/ui` | dashboard operativa | layout e capacità, dati non collegati |
| `/ui/customers` | ricerca e filtri clienti | tabella e stato vuoto |
| `/ui/customers/{customerId}` | scheda cliente | profilo, contatti, identità, indirizzi e ordini collegati |
| `/ui/orders` | ricerca e filtri ordini | tabella e stato vuoto |
| `/ui/orders/{orderId}` | dettaglio ordine | cliente, origine, righe, snapshot indirizzi, delivery e audit |
| `/ui/picking` | sessioni di picking | tabella e stato vuoto |
| `/ui/shipments` | colli, label e tracking | tabella e stato vuoto |
| `/ui/automation` | outbox, retry e dead letter | tabella e stato vuoto |
| `/ui/integrations` | marketplace, Space e GLS | portafoglio pianificato |
| `/ui/audit` | eventi operativi e di sicurezza | tabella e stato vuoto |
| `/ui/users` | utenti, ruoli e MFA | presentazione, azioni disabilitate |
| `/ui/settings` | preferenze operative | presentazione, azioni disabilitate |
| `/ui/profile` | profilo e sessioni | presentazione, dati non esposti |

La route tecnica `/` resta JSON e indica `/ui` come ingresso dell’interfaccia. Gli endpoint `/health/live` e `/health/ready` mantengono responsabilità separate.

## 4. Architettura dell’informazione

La navigazione è divisa in tre gruppi:

1. **Operatività**: dashboard, clienti, ordini, picking e spedizioni;
2. **Controllo**: automazioni, integrazioni e audit;
3. **Amministrazione**: utenti, ruoli e impostazioni.

Il profilo è raggiungibile dalla barra superiore. Ogni pagina presenta:

- contesto ed eventuale breadcrumb;
- titolo e descrizione operativa;
- azione primaria, disabilitata quando il caso d’uso non esiste;
- filtri GET riproducibili tramite URL;
- tabella o scheda di dettaglio;
- stato vuoto che spiega la dipendenza mancante;
- correlation ID nel footer per assistenza e diagnosi.

## 5. Design system

Il design system usa token CSS centralizzati per:

- colori e toni semantici;
- spaziature e raggi;
- ombre e superfici;
- tipografia di sistema;
- larghezza della sidebar e altezza della topbar;
- breakpoint responsive.

Componenti implementati:

- brand e navigazione laterale;
- barra superiore e account chip;
- pulsanti primari, secondari e ghost;
- badge di stato;
- metric card;
- panel e section header;
- toolbar con ricerca e filtro;
- tabelle e paginazione;
- empty state;
- notice informativi e di rischio;
- card integrazione;
- tab e timeline;
- campi login e recupero accesso;
- layout responsive per desktop, tablet e mobile.

I colori non rappresentano mai da soli uno stato: badge e avvisi includono sempre testo. Le azioni indisponibili usano l’attributo `disabled` e una motivazione contestuale.

## 6. Accessibilità

La baseline mira a WCAG 2.2 livello AA e comprende:

- lingua del documento dichiarata;
- landmark e gerarchia dei titoli;
- link “Vai al contenuto”;
- etichette esplicite e label nascoste soltanto visivamente;
- `aria-current` sulla navigazione attiva;
- controlli mobile con stato `aria-expanded`;
- tabelle con header e scope semantico;
- focus visibile;
- contrasto testuale e stati non affidati solo al colore;
- chiusura della navigazione mobile tramite `Escape`;
- navigazione mobile esclusa dal focus quando chiusa e focus confinato mentre è aperta;
- rispetto di `prefers-reduced-motion`;
- dimensioni touch adeguate;
- nessuna dipendenza funzionale da JavaScript per leggere i contenuti.

Prima dell’esercizio reale restano obbligatori test automatici e manuali con tastiera, screen reader, zoom 200% e combinazioni browser-dispositivo supportate.

## 7. Sicurezza dell’interfaccia

Le risposte HTML applicano:

- `Cache-Control: no-store, private`;
- `X-Robots-Tag: noindex, nofollow`;
- Content Security Policy senza script o stili inline;
- blocco esplicito di object, frame e origini non autorizzate nella CSP;
- `X-Frame-Options: DENY`;
- `Cross-Origin-Opener-Policy: same-origin`;
- `Cross-Origin-Resource-Policy: same-origin`;
- `Referrer-Policy` e `Permissions-Policy` restrittive;
- escaping HTML con `ENT_QUOTES | ENT_SUBSTITUTE` e UTF-8;
- risoluzione dei template confinata alla directory autorizzata.

Il JavaScript gestisce soltanto navigazione mobile, chiusura degli avvisi e futura visibilità password. Nessuna decisione di autorizzazione è affidata al client.

Prima di collegare dati o azioni sono obbligatori:

1. sessione autenticata con cookie sicuro;
2. autorizzazione deny-by-default per route e risorsa;
3. CSRF per ogni mutazione;
4. reautenticazione per operazioni ad alto impatto;
5. audit delle consultazioni sensibili e delle azioni;
6. minimizzazione dei view model;
7. paginazione e limiti server-side;
8. protezione dall’esportazione massiva non autorizzata.

## 8. Stati dell’interfaccia

Ogni area deve gestire esplicitamente:

- caricamento;
- risultato disponibile;
- insieme vuoto;
- filtro senza risultati;
- dipendenza non disponibile;
- permesso insufficiente;
- conflitto di versione;
- errore temporaneo con retry sicuro;
- errore definitivo o revisione manuale.

Lo stato corrente usa esclusivamente stati vuoti o “non collegato”. Non vengono inseriti clienti, ordini, utenti, metriche o incidenti fittizi che possano essere confusi con dati operativi.

## 9. Collegamento futuro ai casi d’uso

La progressione prevista è:

1. introdurre autenticazione, sessione e policy;
2. definire view model immutabili per pagina;
3. collegare query applicative read-only;
4. introdurre paginazione e filtri validati;
5. aggiungere comandi mutativi con CSRF, controllo versione e audit;
6. gestire errori tipizzati e messaggi operativi;
7. aggiungere test di autorizzazione e accessibilità;
8. rimuovere il banner di anteprima solo dopo il superamento del gate di fase 8.

Le azioni sensibili — annullamento, retry, replay, ristampa massiva, gestione utenti e secret — non vengono abilitate per semplice disponibilità grafica. Richiedono sempre il relativo caso d’uso protetto e testato.
