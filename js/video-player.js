class CustomVideoPlayer {
    constructor(container, videoUrl, lessonId) {
        this.container = container;
        this.videoUrl = videoUrl;
        this.lessonId = lessonId;
        this.notes = [];
        this.lastUpdateTime = 0;
        this.updateInterval = 5000; // Update progress every 5 seconds
        
        this.init();
    }

    async init() {
        await this.createPlayer();
        await this.loadNotes();
        this.setupControls();
        this.setupNotesPanel();
        this.setupProgressTracking();
    }

    async createPlayer() {
        this.videoElement = document.createElement('video');
        this.videoElement.className = 'w-full rounded-lg shadow-lg';
        this.videoElement.src = this.videoUrl;
        this.videoElement.controls = true;

        // Custom controls container
        const controls = document.createElement('div');
        controls.className = 'mt-4 flex items-center space-x-4';
        controls.innerHTML = `
            <div class="flex items-center space-x-2">
                <button id="playPause" class="p-2 rounded-full bg-blue-500 text-white hover:bg-blue-600">
                    <i class="fas fa-play"></i>
                </button>
                <div class="relative w-48">
                    <input type="range" id="speedControl" min="0.5" max="2" step="0.25" value="1"
                           class="w-full">
                    <span id="speedValue" class="absolute -top-6 left-1/2 transform -translate-x-1/2 text-sm">
                        1x
                    </span>
                </div>
            </div>
            <button id="addNote" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                Add Note
            </button>
        `;

        // Progress bar
        const progress = document.createElement('div');
        progress.className = 'mt-2 relative h-1 bg-gray-200 rounded';
        progress.innerHTML = `
            <div id="progressBar" class="absolute top-0 left-0 h-full bg-blue-500 rounded"></div>
        `;

        this.container.appendChild(this.videoElement);
        this.container.appendChild(progress);
        this.container.appendChild(controls);
    }

    setupControls() {
        const playPauseBtn = this.container.querySelector('#playPause');
        const speedControl = this.container.querySelector('#speedControl');
        const speedValue = this.container.querySelector('#speedValue');
        const addNoteBtn = this.container.querySelector('#addNote');
        const progressBar = this.container.querySelector('#progressBar');

        // Play/Pause
        playPauseBtn.addEventListener('click', () => {
            if (this.videoElement.paused) {
                this.videoElement.play();
                playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
            } else {
                this.videoElement.pause();
                playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
            }
        });

        // Playback speed
        speedControl.addEventListener('input', (e) => {
            const speed = parseFloat(e.target.value);
            this.videoElement.playbackRate = speed;
            speedValue.textContent = speed + 'x';
        });

        // Progress bar
        this.videoElement.addEventListener('timeupdate', () => {
            const progress = (this.videoElement.currentTime / this.videoElement.duration) * 100;
            progressBar.style.width = progress + '%';
        });

        // Add note
        addNoteBtn.addEventListener('click', () => {
            this.videoElement.pause();
            this.showNoteDialog();
        });
    }

    setupNotesPanel() {
        const panel = document.createElement('div');
        panel.className = 'mt-6';
        panel.innerHTML = `
            <h3 class="text-lg font-semibold mb-4 dark:text-white">Video Notes</h3>
            <div id="notesList" class="space-y-4"></div>
        `;
        this.container.appendChild(panel);
        this.notesListElement = panel.querySelector('#notesList');
    }

    async loadNotes() {
        try {
            const response = await fetch(`/api/video_notes.php?lesson_id=${this.lessonId}`);
            const data = await response.json();
            if (data.success) {
                this.notes = data.notes;
                this.renderNotes();
            }
        } catch (error) {
            console.error('Failed to load notes:', error);
        }
    }

    renderNotes() {
        this.notesListElement.innerHTML = '';
        this.notes.sort((a, b) => a.timestamp - b.timestamp);

        this.notes.forEach(note => {
            const noteElement = document.createElement('div');
            noteElement.className = 'p-4 bg-gray-50 dark:bg-gray-700 rounded-lg';
            noteElement.innerHTML = `
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            ${this.formatTime(note.timestamp)}
                        </p>
                        <p class="mt-1 dark:text-white">${note.note_text}</p>
                    </div>
                    <button class="text-blue-500 hover:text-blue-600"
                            onclick="player.seekTo(${note.timestamp})">
                        <i class="fas fa-play"></i>
                    </button>
                </div>
            `;
            this.notesListElement.appendChild(noteElement);
        });
    }

    showNoteDialog() {
        const currentTime = Math.floor(this.videoElement.currentTime);
        const dialog = document.createElement('div');
        dialog.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
        dialog.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-lg w-full">
                <h3 class="text-lg font-semibold mb-4 dark:text-white">
                    Add Note at ${this.formatTime(currentTime)}
                </h3>
                <textarea id="noteText" rows="4" 
                          class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                          placeholder="Enter your note..."></textarea>
                <div class="mt-4 flex justify-end space-x-2">
                    <button id="cancelNote" 
                            class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800">
                        Cancel
                    </button>
                    <button id="saveNote"
                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                        Save Note
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(dialog);

        const noteText = dialog.querySelector('#noteText');
        const saveBtn = dialog.querySelector('#saveNote');
        const cancelBtn = dialog.querySelector('#cancelNote');

        const closeDialog = () => {
            document.body.removeChild(dialog);
            this.videoElement.play();
        };

        saveBtn.addEventListener('click', async () => {
            if (noteText.value.trim()) {
                await this.saveNote(currentTime, noteText.value.trim());
                closeDialog();
            }
        });

        cancelBtn.addEventListener('click', closeDialog);
        noteText.focus();
    }

    async saveNote(timestamp, text) {
        try {
            const response = await fetch('/api/video_notes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lesson_id: this.lessonId,
                    timestamp,
                    note_text: text,
                    csrf_token: document.querySelector('meta[name="csrf-token"]').content
                })
            });

            const data = await response.json();
            if (data.success) {
                this.notes.push({
                    timestamp,
                    note_text: text
                });
                this.renderNotes();
            }
        } catch (error) {
            console.error('Failed to save note:', error);
        }
    }

    setupProgressTracking() {
        this.videoElement.addEventListener('timeupdate', () => {
            const currentTime = Math.floor(this.videoElement.currentTime);
            const now = Date.now();

            if (now - this.lastUpdateTime >= this.updateInterval) {
                this.updateProgress(currentTime);
                this.lastUpdateTime = now;
            }
        });

        this.videoElement.addEventListener('ended', () => {
            this.updateProgress(Math.floor(this.videoElement.duration), true);
        });
    }

    async updateProgress(currentTime, completed = false) {
        try {
            await fetch('/api/video_progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lesson_id: this.lessonId,
                    current_time: currentTime,
                    is_completed: completed,
                    csrf_token: document.querySelector('meta[name="csrf-token"]').content
                })
            });
        } catch (error) {
            console.error('Failed to update progress:', error);
        }
    }

    seekTo(timestamp) {
        this.videoElement.currentTime = timestamp;
        this.videoElement.play();
    }

    formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        seconds = seconds % 60;
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
}

// Usage:
// const player = new CustomVideoPlayer(containerElement, videoUrl, lessonId);
