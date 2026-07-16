# Checklist per modifiche e pull request

## Perimetro e ownership

- [ ] Obiettivo unico e stato reale della capacità dichiarati.
- [ ] Modulo proprietario del dato e del caso d’uso espliciti.
- [ ] Scope account-canale, cliente, ordine o provider definito.
- [ ] Nessun refactor estraneo alla funzionalità.

## Sicurezza e delivery

- [ ] Route e azioni applicano autenticazione e autorizzazione deny-by-default quando operative.
- [ ] Mutazioni protette da CSRF e metodo HTTP appropriato.
- [ ] Dati personali, segreti, payload e label minimizzati e redatti.
- [ ] Errori client non espongono dettagli interni.
- [ ] Funzioni incomplete restano disabilitate e marcate parziali/non operative.

## Livelli applicativi

- [ ] Controller sottile, senza SQL, transazioni o chiamate provider dirette.
- [ ] Caso d’uso applicativo nominato e dipendenze tipizzate.
- [ ] Validator puri separati dalle verifiche di persistenza.
- [ ] Policy di dominio non usate al posto dei permessi route-level.
- [ ] Repository parametrizzati, atomici e privi di contesto HTTP/sessione.
- [ ] Transazione collocata nel service per workflow multi-step.

## Database e consistenza

- [ ] Migrazione testata in salita e strategia di rollback/forward-fix dichiarata.
- [ ] Foreign key, check constraint, unicità e indici coerenti con le query.
- [ ] Dominio e outbox persistiti nella stessa transazione quando necessario.
- [ ] Concorrenza, optimistic locking e idempotenza valutati.
- [ ] Nessun seed demo o reset distruttivo in produzione.

## Moduli e provider

- [ ] Dipendenze cross-module minime e soltanto verso `Contract` pubblici.
- [ ] `config/module-dependencies.php` aggiornato e privo di cicli.
- [ ] Core indipendente dai moduli concreti.
- [ ] Mapping provider confinato nell’adapter.
- [ ] Timeout, retry, rate limit, idempotenza, failure mode e riconciliazione documentati.
- [ ] Nessuna specifica esterna inventata senza discovery verificata.

## Audit e operatività

- [ ] Attore, azione, entità, correlation ID e variazione significativa auditati.
- [ ] Segreti e payload completi esclusi da audit e log.
- [ ] Retry, dead letter, alert ed escalation definiti per gli effetti esterni.
- [ ] Health check, metriche e runbook aggiornati quando pertinenti.

## UI

- [ ] Output escapato e accessibile da tastiera.
- [ ] Stati vuoti e messaggi in italiano coerenti con il comportamento reale.
- [ ] Azioni non operative disabilitate, senza dati dimostrativi ingannevoli.
- [ ] Responsive e focus state verificati.

## Test e qualità

- [ ] Unit test di invarianti, validator, policy e service.
- [ ] Integration test di repository, migrazione, vincoli e rollback.
- [ ] Functional test di route, sicurezza e risposte.
- [ ] Test di ownership e accesso negato quando pertinenti.
- [ ] Suite di conformità per adapter multipli.
- [ ] Architecture check aggiornato.
- [ ] `composer ci:full` verde.
- [ ] Smoke production e migrazioni reali verdi in CI.

## Documentazione e rilascio

- [ ] README, architettura, sicurezza, interfaccia e TODO allineati.
- [ ] ADR aggiunta per una decisione strutturale duratura.
- [ ] Versione minima schema e asset aggiornate quando necessario.
- [ ] Impatto deploy, compatibilità e recovery documentati.
- [ ] Commit descrive chiaramente il risultato.

## Verifica finale

Prima del merge devono essere chiare le risposte a: chi possiede il dato, chi può agire, dove avvengono validazione e transazione, quale audit/outbox viene scritto, cosa accade in caso di errore, quali test lo provano e perché la documentazione descrive lo stato reale.
