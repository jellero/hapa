# Evidenze per la security review Temu

Ultimo aggiornamento: 31 luglio 2026.

## Ambito dichiarato

- applicazione interna: **Spacer Shop Management System**;
- dominio pubblico: `https://admin.hapa.it`;
- IP pubblico in allowlist: `77.81.229.121`;
- infrastruttura dichiarata: server Aruba in Italia;
- dati trattati: nome destinatario, indirizzo, telefono, email e informazioni ordine, esclusivamente per evasione, spedizione e assistenza.

## Controllo implementato

HAPA cifra a riposo i dati personali dei destinatari prima che PostgreSQL completi ogni `INSERT` o `UPDATE`.

Sono cifrati:

- nome visualizzato, nome, cognome e ragione sociale;
- email, telefono, codice fiscale e partita IVA;
- destinatario e componenti dell'indirizzo;
- snapshot di spedizione e fatturazione degli ordini;
- storico cliente;
- payload tecnici inbox/outbox che possono contenere dati ordine;
- file privati di spedizione, comprese le etichette.

La cifratura delle colonne PostgreSQL usa `pgcrypto` con cifrario AES-256 e salt casuale per ciascun valore. I documenti privati usano AES-256-GCM con nonce casuale e autenticazione del ciphertext. La chiave è un segreto base64 di 32 byte caricato da `HAPA_PII_KEY_FILE`, esterno al database, e il file deve avere permessi `0600`.

L'email normalizzata non è conservata in chiaro: viene sostituita da un blind index HMAC-SHA-256. Le sessioni applicative autorizzate impostano la chiave solo nella propria connessione PostgreSQL e decifrano trasparentemente i risultati. Una normale sessione `psql` senza chiave vede esclusivamente i ciphertext.

## Installazione in produzione

Generare la chiave una sola volta e conservarne una copia di backup protetta. La perdita della chiave rende i dati cifrati irrecuperabili.

```bash
umask 077
mkdir -p secrets
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;' > secrets/pii_key.txt
chmod 600 secrets/pii_key.txt
```

Configurazione:

```dotenv
APP_URL=https://admin.hapa.it
HAPA_PII_KEY_FILE=./secrets/pii_key.txt
HAPA_PII_KEY_ID=aruba-it-v1
```

Applicare la migrazione con la stessa chiave che verrà usata dall'applicazione:

```bash
docker compose run --rm migration
```

La migrazione `20260731095000_encrypt_recipient_pii_at_rest.php` è intenzionalmente irreversibile: un rollback automatico riporterebbe dati personali in chiaro.

## Screenshot da allegare a Temu

Dopo deploy e migrazione:

```bash
psql -U hapa -d hapa -f scripts/temu-security-evidence.sql
```

Acquisire uno screenshot dell'output con i nomi completi dei campi. I valori delle colonne cifrate iniziano con `hapa:v1:`; gli snapshot JSON mostrano le proprietà `_hapa_pii` e `_key_id`. È possibile oscurare parzialmente il ciphertext, lasciando visibili il prefisso, i nomi dei campi e il fatto che non siano leggibili.

Per i documenti privati, mostrare un file di etichetta memorizzato sul server:

```bash
head -c 160 /percorso/storage/shipment_label/AAAA/MM/NOMEFILE.pdf
```

Il contenuto deve iniziare con:

```text
HAPA-PII-FILE-V1
```

Non deve apparire il contenuto PDF, ZPL o PNG in chiaro.

## Risposta proposta per I. Data Storage, domanda 1

Selezione: **A. Yes**

Testo da inserire o allegare:

> Recipient PII is encrypted at rest before it is persisted. PostgreSQL fields and JSON order snapshots are encrypted with pgcrypto using AES-256 and per-value random salt. Shipping labels and other private shipping documents are encrypted with AES-256-GCM. The 32-byte encryption key is stored outside the database in a restricted secret file and is injected only into authorized application database sessions. Email lookup uses an HMAC-SHA-256 blind index and does not retain normalized email addresses in plaintext.

Questa risposta deve essere inviata soltanto dopo aver distribuito il codice, creato la chiave, applicato la migrazione e verificato gli screenshot.

## Risposta proposta per I. Data Storage, domanda 2

Se il sistema conserva etichette di spedizione o ricevute, selezione: **A. Yes**.

I file vengono memorizzati in directory private con permessi restrittivi e contenuto AES-256-GCM cifrato. Il download applicativo verifica autorizzazione, riferimento interno e checksum.

## Verifiche aggiuntive prima del reinvio

- `admin.hapa.it` deve essere raggiungibile esclusivamente tramite HTTPS;
- il certificato deve essere valido per `admin.hapa.it`;
- TLS 1.0 e 1.1 devono essere disabilitati;
- l'allowlist Temu deve contenere `77.81.229.121`;
- il provider selezionato deve corrispondere ad Aruba;
- non inserire mai la chiave PII negli screenshot, nei log o nel repository.
