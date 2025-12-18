<?php
// index.php - Public landing dashboard (no login required)
session_start();
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Tachyon - Fast Todos & Notes</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .note-actions {
            display: flex;
            gap: var(--space-sm);
            margin-top: var(--space-sm);
        }

        .note-action-btn {
            padding: 4px 8px;
            font-size: 0.75rem;
            border: 1px solid var(--color-black);
            background-color: transparent;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .note-action-btn:hover {
            background-color: var(--color-black);
            color: var(--color-white);
        }
    </style>
</head>

<body>
    <!-- Dot Matrix Background Pattern -->
    <div class="dot-pattern"></div>

    <div class="dashboard-container">
        <!-- Header -->
        <header class="app-header">
            <h1 class="app-title">TACHYON</h1>
            <div class="user-nav">
                <span class="user-welcome">Guest mode</span>
                <a href="account.php" class="btn btn-sm">Account / Profile</a>
            </div>
        </header>

        <!-- Intro -->
        <section class="dashboard-nav-section">
            <h2 class="section-title">Start instantly</h2>
            <p style="max-width: 520px; margin-top: var(--space-md);">
                Capture todos and notes right away. No signup required. Your data stays in this browser using
                <span class="text-mono">localStorage</span>. Create an account later if you want cloud‑synced features.
            </p>
            <div class="nav-buttons" style="margin-top: var(--space-lg);">
                <button type="button" class="nav-btn" id="goto-todos">[ToDos]</button>
                <button type="button" class="nav-btn" id="goto-notes">[Notes]</button>
                <a href="account.php" class="nav-btn">[Account / Profile]</a>
            </div>
        </section>

        <!-- Layout -->
        <div class="stats-grid" style="margin-top: var(--space-xl);">
            <!-- Anonymous Todos Summary -->
            <div class="stat-card">
                <div class="stat-value" id="anon-total-todos">0</div>
                <div class="stat-label">Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="anon-completed-todos">0</div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="anon-total-notes">0</div>
                <div class="stat-label">Notes</div>
            </div>
        </div>

        <!-- Todos Section -->
        <section class="notes-section" id="todos-section" style="margin-top: var(--space-2xl);">
            <h2 class="section-title">Guest ToDos</h2>
            <p class="mb-4" style="max-width: 520px;">
                These tasks are stored only in this browser. Clear your browser data and they’re gone.
            </p>

            <div class="add-task-card">
                <h3>[+ NEW TASK]</h3>
                <form id="anon-todo-form" class="mt-4" onsubmit="return false;">
                    <div class="task-form-row">
                        <div class="form-group">
                            <label for="anon-task">Task</label>
                            <input type="text" id="anon-task" placeholder="What needs to be done?">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <button type="submit" class="btn btn-primary">[Add Task]</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="todo-list" id="anon-todo-list" style="margin-top: var(--space-xl);">
                <!-- Rendered by JavaScript -->
            </div>
        </section>

        <!-- Notes Section -->
        <section class="notes-section" id="notes-section" style="margin-top: var(--space-2xl);">
            <h2 class="section-title">Guest Notes</h2>
            <p class="mb-4" style="max-width: 520px;">
                Quick notes for this device only. For rich notes, backups, and email features, use your account dashboard.
            </p>

            <div class="add-task-card">
                <h3>[+ NEW NOTE]</h3>
                <form id="anon-note-form" class="mt-4" onsubmit="return false;">
                    <div class="form-group">
                        <label for="anon-note-title">Title</label>
                        <input type="text" id="anon-note-title" placeholder="Note title">
                    </div>
                    <div class="form-group">
                        <label for="anon-note-body">Body</label>
                        <textarea id="anon-note-body" rows="4" placeholder="Write your note..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">[Save Note]</button>
                </form>
            </div>

            <div class="notes-grid" id="anon-notes-grid" style="margin-top: var(--space-xl);">
                <!-- Rendered by JavaScript -->
            </div>
        </section>

        <!-- Callout -->
        <section class="profile-section" style="margin-top: var(--space-2xl);">
            <div class="profile-content">
                <h3>Want more power?</h3>
                <p class="mb-4">
                    Create an account to access your todos and notes from any device, get backups, reminders, and more.
                </p>
                <a href="account.php" class="btn btn-primary">Go to Account / Profile</a>
            </div>
        </section>
    </div>

    <script>
        const TODO_STORAGE_KEY = 'tachyon_anon_todos_v1';
        const NOTES_STORAGE_KEY = 'tachyon_anon_notes_v1';

        function loadFromStorage(key, fallback) {
            try {
                const raw = localStorage.getItem(key);
                if (!raw) return fallback;
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : fallback;
            } catch (e) {
                console.error('Failed to parse localStorage for', key, e);
                return fallback;
            }
        }

        function saveToStorage(key, value) {
            try {
                localStorage.setItem(key, JSON.stringify(value));
            } catch (e) {
                console.error('Failed to save to localStorage for', key, e);
            }
        }

        function renderTodos(todos) {
            const list = document.getElementById('anon-todo-list');
            const totalSpan = document.getElementById('anon-total-todos');
            const completedSpan = document.getElementById('anon-completed-todos');

            totalSpan.textContent = todos.length;
            completedSpan.textContent = todos.filter(t => t.completed).length;

            if (todos.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <h3>No tasks yet!</h3>
                        <p>Add your first task above to get started.</p>
                    </div>
                `;
                return;
            }

            list.innerHTML = '';
            todos.forEach(todo => {
                const item = document.createElement('div');
                item.className = 'todo-item ' + (todo.completed ? 'completed' : '');
                item.innerHTML = `
                    <div class="todo-content">
                        <div class="todo-title"></div>
                        <div class="todo-meta">
                            <span class="badge badge-status">${todo.completed ? 'Completed' : 'Pending'}</span>
                        </div>
                    </div>
                    <div class="todo-actions">
                        <button class="btn btn-success btn-sm" data-action="toggle">${todo.completed ? '[Uncomplete]' : '[Complete]'}</button>
                        <button class="btn btn-danger btn-sm" data-action="delete">[Delete]</button>
                    </div>
                `;
                item.querySelector('.todo-title').textContent = todo.text;

                item.addEventListener('click', (e) => {
                    const action = e.target.getAttribute('data-action');
                    if (!action) return;
                    e.stopPropagation();

                    const idx = todos.findIndex(t => t.id === todo.id);
                    if (idx === -1) return;

                    if (action === 'toggle') {
                        todos[idx].completed = !todos[idx].completed;
                    } else if (action === 'delete') {
                        todos.splice(idx, 1);
                    }
                    saveToStorage(TODO_STORAGE_KEY, todos);
                    renderTodos(todos);
                });

                list.appendChild(item);
            });
        }

        function renderNotes(notes) {
            const grid = document.getElementById('anon-notes-grid');
            const totalSpan = document.getElementById('anon-total-notes');
            totalSpan.textContent = notes.length;

            if (notes.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📝</div>
                        <div class="empty-state-text">No notes yet</div>
                        <p>Create your first note above to get started!</p>
                    </div>
                `;
                return;
            }

            grid.innerHTML = '';
            notes.forEach(note => {
                const card = document.createElement('div');
                card.className = 'note-card';
                const created = note.createdAt ? new Date(note.createdAt) : null;
                const createdLabel = created ? created.toLocaleString() : '';
                card.innerHTML = `
                    <h3 class="note-title"></h3>
                    <div class="note-preview"></div>
                    <div class="note-meta">
                        <span class="note-date">${createdLabel}</span>
                    </div>
                    <div class="note-actions">
                        <button class="note-action-btn" data-action="delete">Delete</button>
                    </div>
                `;
                card.querySelector('.note-title').textContent = note.title || 'Untitled';
                card.querySelector('.note-preview').textContent = note.body || '';

                card.addEventListener('click', (e) => {
                    const action = e.target.getAttribute('data-action');
                    if (action === 'delete') {
                        e.stopPropagation();
                        const idx = notes.findIndex(n => n.id === note.id);
                        if (idx !== -1) {
                            notes.splice(idx, 1);
                            saveToStorage(NOTES_STORAGE_KEY, notes);
                            renderNotes(notes);
                        }
                    }
                });

                grid.appendChild(card);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Smooth scroll buttons
            const todosBtn = document.getElementById('goto-todos');
            const notesBtn = document.getElementById('goto-notes');
            const todosSection = document.getElementById('todos-section');
            const notesSection = document.getElementById('notes-section');

            if (todosBtn && todosSection) {
                todosBtn.addEventListener('click', () => {
                    todosSection.scrollIntoView({ behavior: 'smooth' });
                });
            }
            if (notesBtn && notesSection) {
                notesBtn.addEventListener('click', () => {
                    notesSection.scrollIntoView({ behavior: 'smooth' });
                });
            }

            // Initial data
            const todos = loadFromStorage(TODO_STORAGE_KEY, []);
            const notes = loadFromStorage(NOTES_STORAGE_KEY, []);

            renderTodos(todos);
            renderNotes(notes);

            // Todo form
            const todoForm = document.getElementById('anon-todo-form');
            const todoInput = document.getElementById('anon-task');

            todoForm.addEventListener('submit', () => {
                const text = (todoInput.value || '').trim();
                if (!text) return;
                todos.unshift({
                    id: Date.now().toString(36) + Math.random().toString(36).slice(2),
                    text,
                    completed: false,
                    createdAt: new Date().toISOString()
                });
                todoInput.value = '';
                saveToStorage(TODO_STORAGE_KEY, todos);
                renderTodos(todos);
            });

            // Note form
            const noteForm = document.getElementById('anon-note-form');
            const noteTitle = document.getElementById('anon-note-title');
            const noteBody = document.getElementById('anon-note-body');

            noteForm.addEventListener('submit', () => {
                const title = (noteTitle.value || '').trim();
                const body = (noteBody.value || '').trim();
                if (!title && !body) return;

                notes.unshift({
                    id: Date.now().toString(36) + Math.random().toString(36).slice(2),
                    title,
                    body,
                    createdAt: new Date().toISOString()
                });

                noteTitle.value = '';
                noteBody.value = '';
                saveToStorage(NOTES_STORAGE_KEY, notes);
                renderNotes(notes);
            });
        });
    </script>
</body>

</html>