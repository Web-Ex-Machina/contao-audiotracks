/**
 * Audiotracks player (vanilla JS)
 *
 * Handles the global audio player, the likes and the listening session sync.
 */
(() => {
    'use strict';

    const STORAGE_DATA = 'wem_audiotracks_data';
    const STORAGE_VOLUME = 'wem_audiotracks_globalvolume';

    const readStorage = (key) => {
        try {
            return JSON.parse(localStorage.getItem(key));
        } catch (e) {
            return null;
        }
    };

    const writeStorage = (key, value) => {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            // Storage unavailable (private mode, quota...), ignore
        }
    };

    const formatTime = (seconds) => {
        if (!Number.isFinite(seconds)) {
            return '00:00';
        }

        const minutes = Math.floor(seconds / 60);
        const rest = Math.round(seconds % 60);

        return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
    };
    /**
     * The request token is not in the (cached) page: it comes with the state of the tracks.
     */
    const ensureToken = async (player) => {
        if (!player.dataset.requestToken) {
            const response = await fetch(player.dataset.urlState, { credentials: 'same-origin', cache: 'no-store' });
            const { requestToken } = await response.json();

            player.dataset.requestToken = requestToken;
        }

        return player.dataset.requestToken;
    };

    /**
     * POST to the bundle ajax route, answers with JSON.
     */
    const post = async (player, action, data) => {
        const body = new URLSearchParams({
            TL_AJAX: '1',
            REQUEST_TOKEN: await ensureToken(player),
            ...data,
        });

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);

        try {
            const response = await fetch(action === 'feedback' ? player.dataset.urlFeedback : player.dataset.urlSyncSession, {
                method: 'POST',
                body,
                signal: controller.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const result = await response.json();

            if ('success' !== result.status) {
                throw new Error(result.message || 'Request failed');
            }

            return result;
        } finally {
            clearTimeout(timeout);
        }
    };

    const initFilters = () => {
        document.querySelectorAll('.audiotracks__filters [data-auto-submit]').forEach((select) => {
            select.addEventListener('change', () => select.form.submit());
        });
    };

    const listenedEl = (id) => document.querySelector(`.audiotrack[data-audiotrack="${id}"] .audiotrack__listened`);
    const rowEl = (id) => document.querySelector(`[data-audiotrack="${id}"].audiotrack, [data-audiotrack="${id}"].audiotrack_full`);

    // Row state: "started" once some progress exists, "complete" once listened
    const markRow = (id, started, complete) => {
        const row = rowEl(id);

        if (row) {
            row.classList.toggle('started', (started || complete) && !complete);
            row.classList.toggle('complete', complete);
        }
    };

    /**
     * The pages do not contain anything that depends on the visitor (so they can be cached),
     * the likes and the listening sessions are loaded here, once the page is displayed.
     */
    const applyTrackState = (id, state) => {
        document.querySelectorAll(`.audiotrack__likes[data-id="${id}"]`).forEach((button) => {
            button.classList.toggle('liked', !!state.liked);

            const count = button.querySelector('.count');

            if (count) {
                count.textContent = state.likes > 0 ? state.likes : '';
            }
        });

        const session = state.session;

        if (!session) {
            return;
        }

        document.querySelectorAll(`.audiotrack__play[data-id="${id}"]`).forEach((button) => {
            button.dataset.currentTime = session.currentTime;
            button.dataset.volume = session.volume;
            button.dataset.complete = session.complete ? '1' : '0';
        });

        markRow(id, session.currentTime > 0, session.complete);

        if (session.complete) {
            listenedEl(id)?.classList.add('active');
        }
    };

    const loadState = async (playerEl) => {
        const ids = [...new Set([...document.querySelectorAll('.audiotrack__play')].map((b) => b.dataset.id))];

        if (!ids.length || !playerEl.dataset.urlState) {
            return;
        }

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 5000);

        try {
            const response = await fetch(`${playerEl.dataset.urlState}?ids=${ids.join(',')}`, {
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
            });

            if (!response.ok) {
                return;
            }

            const { requestToken, tracks } = await response.json();

            playerEl.dataset.requestToken = requestToken;

            Object.entries(tracks).forEach(([id, state]) => applyTrackState(id, state));
        } catch (e) {
            // The player still works with the default values and the local storage
            console.warn(e);
        } finally {
            clearTimeout(timeout);
        }
    };

    const initPlayer = (playerEl) => {
        const playButtons = [...document.querySelectorAll('.audiotrack__play')];

        const trackList = playButtons.map((button) => ({
            id: button.dataset.id,
            title: button.dataset.title,
            subtitle: button.dataset.subtitle || "",
            picture: button.dataset.picture || "",
            src: button.dataset.src,
            currentTime: parseFloat(button.dataset.currentTime) || 0,
            volume: button.dataset.volume ? parseFloat(button.dataset.volume) : 1,
            complete: '1' === button.dataset.complete,
        }));

        const findTrack = (id) => trackList.find((t) => t.id === String(id));
        let localData = readStorage(STORAGE_DATA);

        if (!Array.isArray(localData) || localData.length !== trackList.length) {
            localData = trackList.map((t) => ({ ...t }));
        } else {
            for (const stored of localData) {
                const track = findTrack(stored.id);

                if (track) {
                    track.currentTime = stored.currentTime;
                    track.volume = stored.volume;
                    track.complete = stored.complete;
                }

                if (stored.complete) {
                    listenedEl(stored.id)?.classList.add('active');
                    markRow(stored.id, true, true);
                }
            }
        }

        const trackBar = playerEl.querySelector('.audioPlayer__track');
        const volumeBar = playerEl.querySelector('.audioPlayer__volume');
        const muteButton = playerEl.querySelector('.audioPlayer__mute');
        const nextButton = playerEl.querySelector('.audioPlayer__button.next');
        const prevButton = playerEl.querySelector('.audioPlayer__button.prev');
        const playButton = playerEl.querySelector('.audioPlayer__button.play');
        const titleEl = playerEl.querySelector('.audioPlayer__title');
        const subtitleEl = playerEl.querySelector('.audioPlayer__subtitle');
        const coverEl = playerEl.querySelector('.audioPlayer__cover');
        const currentEl = playerEl.querySelector('.audioPlayer__current');
        const durationEl = playerEl.querySelector('.audioPlayer__duration');

        const audio = new Audio();
        let currentTrack = null;
        let prevTrack = null;
        let nextTrack = null;
        let globalVolume = parseFloat(readStorage(STORAGE_VOLUME)) || 1;
        let syncTimer;
        let volumeTimer;

        const setPlayerButtons = () => {
            const index = trackList.indexOf(currentTrack);

            nextTrack = index < trackList.length - 1 ? trackList[index + 1] : null;
            prevTrack = index > 0 ? trackList[index - 1] : null;

            nextButton.classList.toggle('disabled', !nextTrack);
            nextButton.title = nextTrack ? nextTrack.title : '';
            prevButton.classList.toggle('disabled', !prevTrack);
            prevButton.title = prevTrack ? prevTrack.title : '';
        };

        const playTrack = (track) => {
            if (!track || !track.src) {
                return;
            }

            currentTrack = track;
            audio.src = track.src;
            volumeBar.value = globalVolume;
            audio.volume = globalVolume;
            audio.currentTime = track.currentTime;
            playerEl.classList.add('active');
            setPlayerButtons();
            audio.play();
        };

        const syncSession = async () => {
            if (!currentTrack) {
                return;
            }

            if (audio.duration - audio.currentTime < 10 || currentTrack.complete) {
                currentTrack.complete = true;
                listenedEl(currentTrack.id)?.classList.add('active');
            }

            const data = localData.find((t) => t.id === currentTrack.id);

            if (data) {
                data.currentTime = audio.currentTime;
                data.volume = globalVolume = audio.volume;
                data.complete = currentTrack.complete;
            }

            markRow(currentTrack.id, audio.currentTime > 0, currentTrack.complete);

            writeStorage(STORAGE_VOLUME, globalVolume);
            writeStorage(STORAGE_DATA, localData);

            await post(playerEl, 'syncSession', {
                audiotrack: currentTrack.id,
                currentTime: audio.currentTime,
                volume: audio.volume,
                complete: currentTrack.complete,
            });
        };

        const safeSync = () => syncSession().catch((e) => console.warn(e));

        const setPlaying = (id, playing) => {
            playButtons.forEach((b) => b.classList.remove('playing'));

            if (playing) {
                playButtons.filter((b) => b.dataset.id === id).forEach((b) => b.classList.add('playing'));
            }

            playerEl.classList.toggle('playing', playing);
        };

        // Track buttons in the list
        playButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const track = findTrack(button.dataset.id);

                if (track !== currentTrack) {
                    playTrack(track);
                } else {
                    playButton.click();
                }
            });
        });

        // Player controls
        playButton.addEventListener('click', () => {
            if (!playerEl.classList.contains('playing') && currentTrack) {
                audio.play();
            } else {
                audio.pause();
            }
        });
        nextButton.addEventListener('click', () => nextTrack && playTrack(nextTrack));
        prevButton.addEventListener('click', () => prevTrack && playTrack(prevTrack));

        const startSeek = () => trackBar.classList.add('seeking');
        const endSeek = () => {
            trackBar.classList.remove('seeking');
            // Wait for the audio element to register that the time has changed
            setTimeout(safeSync, 100);
        };
        trackBar.addEventListener('pointerdown', startSeek);
        trackBar.addEventListener('pointerup', endSeek);
        trackBar.addEventListener('change', () => {
            if (currentTrack) {
                audio.currentTime = trackBar.value;
            }
        });

        volumeBar.addEventListener('input', () => {
            audio.volume = volumeBar.value;
        });
        muteButton.addEventListener('click', () => {
            muteButton.classList.toggle('mute');
            audio.muted = muteButton.classList.contains('mute');
        });

        // Audio events
        audio.addEventListener('volumechange', () => {
            clearTimeout(volumeTimer);
            volumeTimer = setTimeout(safeSync, 500);
        });

        audio.addEventListener('play', () => {
            setPlaying(currentTrack.id, true);
            titleEl.title = currentTrack.title;
            titleEl.textContent = currentTrack.title;
            subtitleEl.textContent = currentTrack.subtitle;

            if (currentTrack.picture) {
                coverEl.src = currentTrack.picture;
                coverEl.alt = currentTrack.title;
                coverEl.hidden = false;
            } else {
                coverEl.hidden = true;
            }
            clearInterval(syncTimer);

            syncSession()
                .then(() => {
                    syncTimer = setInterval(safeSync, 10000);
                })
                .catch((e) => console.warn(e));
        });

        audio.addEventListener('pause', () => {
            safeSync();
            clearInterval(syncTimer);
            setPlaying(null, false);
        });

        audio.addEventListener('loadeddata', () => {
            trackBar.min = 0;
            trackBar.max = audio.duration;
            trackBar.style.cssText = `--min: 0; --max: ${Math.floor(audio.duration)}; --val: ${Math.floor(audio.currentTime)}`;
            durationEl.textContent = formatTime(audio.duration);
        });

        audio.addEventListener('timeupdate', () => {
            if (!trackBar.classList.contains('seeking')) {
                trackBar.value = Math.floor(audio.currentTime);
                trackBar.style.setProperty('--val', audio.currentTime);
            }

            currentEl.textContent = formatTime(audio.currentTime);
        });

        // Likes
        document.querySelectorAll('.audiotrack__likes').forEach((button) => {
            button.addEventListener('click', async () => {
                button.classList.add('no-events');
                const liked = !button.classList.contains('liked');

                try {
                    await post(playerEl, 'feedback', {
                        liked: liked ? 'true' : 'false',
                        audiotrack: button.dataset.id,
                    });

                    button.classList.toggle('liked', liked);

                    const count = button.querySelector('.count');

                    if (count) {
                        const value = (parseInt(count.textContent, 10) || 0) + (liked ? 1 : -1);
                        count.textContent = value > 0 ? value : '';
                    }
                } catch (e) {
                    console.warn(e);
                } finally {
                    button.classList.remove('no-events');
                }
            });
        });

        // Warn the user if the tab is closed while a file is playing
        window.addEventListener('beforeunload', (e) => {
            if (playerEl.classList.contains('playing')) {
                e.preventDefault();
            }
        });
    };

    const init = () => {
        initFilters();

        const playerEl = document.querySelector('[data-audiotracks-player]');

        if (playerEl) {
            // The player needs the listening sessions to start where the visitor stopped
            loadState(playerEl).finally(() => initPlayer(playerEl));
        }
    };

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
