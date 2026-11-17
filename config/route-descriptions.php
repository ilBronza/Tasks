<?php

$prefix = (string) config('tasks.routePrefix');

return [
    'tasks.sync.open' => 'Riceve una segnalazione da un gestionale registrato.',
    'tasks.sync.message' => 'Riceve un messaggio condiviso dal gestionale di origine.',
    'tasks.sync.changes' => 'Restituisce gli aggiornamenti destinati al gestionale autenticato.',
    $prefix . 'projects.index' => 'Elenca i progetti delle attività.',
    $prefix . 'projects.create' => 'Mostra il modulo per creare un progetto.',
    $prefix . 'projects.store' => 'Salva un nuovo progetto.',
    $prefix . 'projects.edit' => 'Mostra il modulo di modifica del progetto.',
    $prefix . 'projects.update' => 'Aggiorna il nome del progetto.',
    $prefix . 'closed' => 'Mostra le attività chiuse.',
    $prefix . 'index' => 'Mostra le attività non chiuse in tabella, elenco o bacheca.',
    $prefix . 'store' => 'Salva una nuova attività.',
    $prefix . 'create' => 'Mostra il modulo per creare un’attività.',
    $prefix . 'edit' => 'Mostra il modulo di modifica dell’attività.',
    $prefix . 'update' => 'Salva le modifiche all’attività.',
    $prefix . 'status' => 'Cambia lo stato dell’attività.',
    $prefix . 'assignment' => 'Assegna o rimuove il responsabile dell’attività e registra l’eventuale commento.',
    $prefix . 'conversation.index' => 'Restituisce i messaggi e i partecipanti alla conversazione dell’attività.',
    $prefix . 'conversation.store' => 'Aggiunge un messaggio con eventuali allegati alla conversazione dell’attività.',
    $prefix . 'conversation.attachment' => 'Scarica un allegato di un messaggio della conversazione dell’attività.',
    $prefix . 'participants.store' => 'Aggiunge un utente esistente ai partecipanti dell’attività tramite indirizzo email.',
    $prefix . 'participants.destroy' => 'Rimuove un partecipante dall’attività.',
    $prefix . 'destroy' => 'Elimina l’attività selezionata.',
];
