# ADR 0001 — Contratto corrieri provider-neutral

- Stato: accettata
- Data: 16 luglio 2026

## Contesto

Il primo contratto di spedizione era collocato nel modulo `Gls`, mentre HAPA deve supportare anche BRT (Bartolini) e potenzialmente altri corrieri. Lasciare richiesta e risultato nel namespace GLS renderebbe il dominio comune dipendente dal primo provider e favorirebbe duplicazioni o import cross-module impropri.

## Decisione

Il modulo `Shipping` possiede `CarrierCode`, `CarrierAdapter`, `ShipmentRequest` e `ShipmentResult`. I moduli `Gls` e `Brt` espongono contratti specifici che estendono `CarrierAdapter` e dipendono esclusivamente da `Shipping\Contract`.

Le dipendenze sono dichiarate in `config/module-dependencies.php` e verificate automaticamente. Il valore dello stato ordine diventa `ready_for_carrier`; i codici persistiti ammessi sono `GLS` e `BRT`.

Endpoint, autenticazione, payload, servizi e opzioni specifiche restano fuori dal contratto comune. Verranno aggiunti soltanto dopo discovery verificata per ciascun provider.

## Conseguenze

- casi d’uso e dominio logistico restano indipendenti dal vettore;
- ogni provider mantiene mapping, configurazione, errori e client propri;
- una suite di conformità potrà essere riutilizzata da tutti gli adapter;
- aggiungere un corriere richiederà enum, modulo, manifesto, migrazione e documentazione coerenti;
- il contratto corrente resta parziale finché colli tipizzati, adapter reali, outbox e riconciliazione non sono implementati.

## Alternative scartate

- mantenere i DTO in `Gls`: confonde ownership comune e provider-specifica;
- duplicare i DTO in `Brt`: introduce divergenza semantica;
- aggiungere campi API BRT senza specifiche verificate: cristallizza assunzioni non affidabili nel contratto pubblico.
