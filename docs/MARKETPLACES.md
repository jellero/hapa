# Strategia integrazioni marketplace

Ultimo riesame: 17 luglio 2026.

## 1. Scopo e stato

Questo documento definisce il modello applicativo HAPA per canali, connettori e account marketplace. L’esecuzione tecnica degli adapter appartiene al repository autonomo `jellero/hapa-automation`.

IBS è il canale commercialmente attivo di HAPA. L’attuale codebase non contiene ancora un adapter IBS reale verificato end-to-end: la migrazione del flusso esistente verso questa architettura deve quindi avvenire come pilot controllato. Temu e Amazon sono i prossimi canali pianificati.

La distinzione fondamentale è:

- **canale di vendita**: il marketplace sul quale nasce l’ordine;
- **connettore**: il percorso tecnico usato per comunicare con il canale;
- **account venditore**: la singola configurazione autorizzata, con perimetro e policy propri.

SellRapido, se usato, è un connettore aggregatore, non un canale. Amazon, Temu e IBS sono canali. Per uno stesso account e canale può essere attivo un solo writer per capacità.

## 2. Ownership

HAPA possiede:

- configurazione applicativa di canali, connettori e account;
- identità canonica del canale sorgente;
- clienti, ordini e stato applicativo;
- prezzo finale desiderato e quantità pubblicabile;
- autorizzazioni, decisioni manuali e audit;
- comandi ed eventi prodotti nella transactional outbox;
- applicazione idempotente degli esiti ricevuti.

`hapa-automation` possiede:

- credenziali tecniche e secret degli account provider;
- adapter diretti o SellRapido;
- polling, webhook tecnici, paginazione e cursori;
- chiamate di import, accettazione, offerta, tracking e fulfilment;
- rate limit, retry, dead letter e riconciliazione;
- metriche tecniche per account e capacità.

HAPA non importa codice adapter, non include il Compose del servizio e non accede al suo database.

## 3. Portafoglio pianificato

| Elemento | Ruolo applicativo | Esecuzione futura | Stato |
|---|---|---|---|
| IBS | canale di vendita corrente | adapter diretto o connettore esistente da verificare | business attivo, integrazione HAPA da migrare |
| Temu | prossimo canale | percorso partner formalmente supportato | pianificato |
| Amazon | prossimo canale | adapter diretto o aggregatore verificato | pianificato |
| SellRapido | eventuale connettore aggregatore | adapter in `hapa-automation` | da confermare per account/capacità |

La scelta del percorso avviene per singolo account e capacità. Non vengono mantenuti due import o due publisher concorrenti sulla stessa coppia account-canale.

## 4. Identità canonica

Codici applicativi iniziali:

```text
channel:   ibs | temu | amazon
connector: ibs | temu | amazon | sellrapido
```

L’identità esterna di un ordine comprende almeno:

```text
account configurato + canale + external_order_id
```

Il connettore descrive il percorso tecnico. Un ordine Amazon ricevuto tramite SellRapido resta un ordine del canale Amazon.

Ogni record applicativo marketplace deve rappresentare una sola coppia account-canale. Più record possono condividere lo stesso connettore senza perdere l’identità del canale sorgente.

## 5. Contratto applicativo

I contratti HAPA definiscono DTO e capacità, non client HTTP concreti. Devono descrivere:

- account e canali supportati;
- capacità abilitate;
- identità ordine e offerta;
- input e risultato normalizzati;
- idempotency key;
- versione applicativa e versione remota;
- classificazione dell’esito;
- dati personali strettamente necessari.

Payload provider, autenticazione, firma, paginazione e codici HTTP restano negli adapter di `hapa-automation`.

Prezzo base e stock ricevuti da Space non vengono sovrascritti dal marketplace. HAPA produce prezzo finale e quantità desiderati; il servizio esterno esegue la pubblicazione e restituisce l’esito.

## 6. Flussi RabbitMQ da definire

Ordini:

```text
hapa-automation importa dal provider
  -> marketplace.order.observed
  -> HAPA persiste ordine e cliente
  -> HAPA produce eventuali comandi
  -> hapa-automation esegue accettazione/invio/tracking
  -> eventi di esito verso HAPA
```

Offerte:

```text
HAPA calcola e versiona l’offerta
  -> marketplace.offer.publish.requested
  -> hapa-automation chiama il connettore attivo
  -> marketplace.offer.published | marketplace.offer.failed
  -> HAPA applica stato e versione remota
```

I comandi contengono prezzo e quantità già decisi da HAPA. Automation non riceve le regole di ricarico e non ricalcola il prezzo. Routing key e payload devono essere congelati in test di contratto condivisi prima dell’attivazione.

## 7. Gate di discovery

Prima di sviluppare un adapter devono essere disponibili e versionati:

1. account sandbox o ambiente di test;
2. documentazione tecnica ufficiale o contratto del partner;
3. autorizzazioni richieste e procedura di rotazione/revoca;
4. operazioni realmente abilitate per l’account;
5. limiti di quota, paginazione e finestre temporali;
6. semantica degli stati ordine e delle quantità parziali;
7. accesso all’indirizzo e vincoli sui dati personali;
8. modalità di invio tracking e fulfilment;
9. operazioni prezzo/stock, atomicità, valuta e arrotondamento;
10. comportamento dopo timeout, duplicati e risposte ambigue;
11. webhook, firma, anti-replay e riconciliazione.

Le capacità non confermate restano disabilitate.

## 8. Sequenza di implementazione

Per ogni connettore:

1. congelare le specifiche;
2. produrre una matrice operazione × capacità × errore;
3. definire contratti RabbitMQ e DTO normalizzati;
4. aggiungere test producer HAPA e consumer `hapa-automation`;
5. implementare fake adapter e suite di conformità nel servizio esterno;
6. implementare client, autenticazione, timeout, quote e redazione;
7. verificare idempotenza, duplicati e recovery;
8. implementare consumer HAPA degli esiti;
9. eseguire un pilot su un solo account-canale;
10. abilitare gradualmente con metriche e arresto rapido.

## 9. Prevenzione dei writer concorrenti

Il passaggio da SellRapido a un adapter diretto, o viceversa, richiede:

1. disabilitazione del vecchio job nel database `hapa-automation`;
2. salvataggio del watermark finale;
3. riconciliazione degli ordini e delle offerte aperte;
4. associazione degli identificativi già conosciuti;
5. attivazione del nuovo connettore da un watermark concordato;
6. verifica dei duplicati;
7. audit applicativo HAPA e audit tecnico nel servizio esterno.

L’invio di tracking e fulfilment continua sul percorso proprietario dell’ordine finché la migrazione non è completata. Il vecchio publisher offerte viene arrestato e riconciliato prima di abilitare il nuovo.

## 10. Stato verificato

- IBS è il canale business attivo, ma l’adapter reale non è ancora presente in questa codebase;
- i tipi canale/connettore e i contratti iniziali sono presenti in HAPA;
- `hapa-automation` dispone della foundation RabbitMQ e PostgreSQL;
- i job provider sono disabilitati;
- non esistono adapter reali né account sandbox configurati;
- relay/consumer HAPA e test di contratto congiunti sono ancora mancanti;
- nessun adapter di questa codebase deve essere descritto come operativo prima del pilot IBS e della riconciliazione con il flusso oggi in uso.
