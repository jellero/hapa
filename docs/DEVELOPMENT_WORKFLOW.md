# Sviluppare una funzionalità HAPA

Ultimo riesame: 16 luglio 2026.

## Prima del codice

Per ogni funzionalità dichiarare:

| Decisione | Domanda |
|---|---|
| dato | HAPA o `hapa-automation` ne è proprietario? |
| caso d’uso | quale servizio coordina l’azione? |
| attore | serve una decisione utente o è un processo tecnico? |
| scope | account, canale, SKU, cliente, ordine o provider? |
| transazione | quali scritture devono riuscire insieme nello stesso database? |
| messaggio | quale evento o comando attraversa RabbitMQ? |
| idempotenza | quale chiave impedisce effetti duplicati? |
| sicurezza | quali dati vanno minimizzati, redatti e auditati? |
| stato | implementato, parziale o pianificato? |

## Regola di ownership

Una funzionalità resta in HAPA quando modifica dati autorevoli o richiede interazione applicativa, per esempio:

- anagrafica prodotto;
- prezzo e stock applicati al prodotto;
- regole di ricarico;
- clienti e ordini;
- picking e decisioni manuali;
- utenti, autorizzazioni e audit.

Una funzionalità appartiene a `hapa-automation` quando esegue lavoro tecnico asincrono, per esempio:

- polling di Space o marketplace;
- scheduling;
- retry provider e dead letter;
- cursori e watermark;
- pubblicazione offerte;
- creazione etichette e fulfilment;
- riconciliazione tecnica.

Non spostare una regola commerciale nel worker e non introdurre accessi diretti al database dell’altro servizio.

## Percorso HAPA

```text
HTTP/UI o messaggio RabbitMQ
  -> validazione e autorizzazione
  -> caso d’uso applicativo
  -> dominio
  -> repository PostgreSQL HAPA
  -> transactional outbox
  -> evento versionato
```

Il consumer RabbitMQ HAPA deve registrare prima il `message_id` in una inbox locale e applicare l’aggiornamento nello stesso transaction boundary.

## Percorso asincrono

```text
evento/comando HAPA
  -> RabbitMQ
  -> inbox hapa-automation
  -> proiezione locale
  -> adapter provider
  -> outbox hapa-automation
  -> RabbitMQ
  -> esito applicato da HAPA
```

## Dipendenze

Il dominio non dipende da RabbitMQ, HTTP client o payload provider. I contratti applicativi usano DTO tipizzati; gli adapter trasformano i payload esterni.

Gli eventi condivisi devono avere un repository o pacchetto di contratto versionato, oppure test di contratto eseguiti in entrambi i repository. Non copiare array non documentati tra servizi.

## Definition of Done

Una vertical slice HAPA è completa quando:

- ownership e confine di transazione sono espliciti;
- schema e migrazione sono presenti;
- casi d’uso e autorizzazioni sono implementati;
- messaggi sono versionati e idempotenti;
- test unitari, integrazione e contratto coprono successo, duplicato e fallimento;
- UI e audit riflettono lo stato reale;
- documentazione e roadmap sono aggiornate;
- nessun documento dichiara operativo un adapter ancora simulato.

Per una modifica che coinvolge entrambi i repository, aprire due PR coordinate e indicare compatibilità e ordine di deploy.
