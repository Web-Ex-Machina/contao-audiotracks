/**
 * Audiotracks player (vanilla JS)
 *
 * Handles the global audio player, the likes and the listening session sync.
 */
(() => {
    'use strict';

    // Every module of the page includes this script: the player is only set up once
    if (window.wemAudiotracksLoaded) {
        return;
    }

    window.wemAudiotracksLoaded = true;

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
    let tokenRequest = null;

    const ensureToken = async (player) => {
        if (!player.dataset.requestToken) {
            // One request at a time, even if the visitor acts before the state is loaded
            tokenRequest ??= fetch(player.dataset.urlState, { credentials: 'same-origin', cache: 'no-store' })
                .then((response) => response.json())
                .finally(() => {
                    tokenRequest = null;
                });

            player.dataset.requestToken = (await tokenRequest).requestToken;
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

    // A track can be displayed by several modules of the page: all its rows are updated
    const markListened = (id) => document.querySelectorAll(`.audiotrack[data-audiotrack="${id}"] .audiotrack__listened`).forEach((el) => el.classList.add('active'));
    const rowEls = (id) => document.querySelectorAll(`[data-audiotrack="${id}"].audiotrack, [data-audiotrack="${id}"].audiotrack_full`);

    // Row state: "started" once some progress exists, "complete" once listened
    const markRow = (id, started, complete) => {
        rowEls(id).forEach((row) => {
            row.classList.toggle('started', (started || complete) && !complete);
            row.classList.toggle('complete', complete);
        });
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
            markListened(id);
        }
    };

    const loadState = async (playerEl, player) => {
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

            Object.entries(tracks).forEach(([id, state]) => {
                applyTrackState(id, state);
                player?.applySession(id, state.session);
            });
        } catch (e) {
            // The player still works with the default values and the local storage
            console.warn(e);
        } finally {
            clearTimeout(timeout);
        }
    };

    const initPlayer = (playerEl) => {
        const playButtons = [...document.querySelectorAll('.audiotrack__play')];

        // One queue for all the modules of the page, in the order of the page, a track displayed twice is queued once
        const queuedButtons = playButtons.filter((button, index) => playButtons.findIndex((b) => b.dataset.id === button.dataset.id) === index);

        const trackList = queuedButtons.map((button) => ({
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
        // The progress kept in the browser wins over the one of the server
        const storedIds = new Set();

        if (!Array.isArray(localData) || localData.length !== trackList.length) {
            localData = trackList.map((t) => ({ ...t }));
        } else {
            for (const stored of localData) {
                const track = findTrack(stored.id);

                if (track) {
                    storedIds.add(track.id);
                    track.currentTime = stored.currentTime;
                    track.volume = stored.volume;
                    track.complete = stored.complete;
                }

                if (stored.complete) {
                    markListened(stored.id);
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

            if ('mediaSession' in navigator) {
                try {
                    navigator.mediaSession.setActionHandler('previoustrack', prevTrack ? () => playTrack(prevTrack) : null);
                    navigator.mediaSession.setActionHandler('nexttrack', nextTrack ? () => playTrack(nextTrack) : null);
                } catch (e) {
                    // Action not supported by this browser
                }
            }
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
                markListened(currentTrack.id);
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

        // Media Session: title and cover on the lock screen / notifications, headphones and keyboard media keys
        const mediaSession = 'mediaSession' in navigator ? navigator.mediaSession : null;

        const updateMediaSession = () => {
            if (!mediaSession || !currentTrack) {
                return;
            }

            const artwork = currentTrack.picture ? [{ src: new URL(currentTrack.picture, window.location.href).href }] : [];

            mediaSession.metadata = new MediaMetadata({
                title: currentTrack.title,
                artist: currentTrack.subtitle,
                album: document.title,
                artwork,
            });
        };

        const updatePositionState = () => {
            if (mediaSession && currentTrack && Number.isFinite(audio.duration) && audio.duration > 0) {
                try {
                    mediaSession.setPositionState({
                        duration: audio.duration,
                        playbackRate: audio.playbackRate || 1,
                        position: Math.min(audio.currentTime, audio.duration),
                    });
                } catch (e) {
                    // Some browsers refuse an inconsistent position, the lock screen just shows no progress
                }
            }
        };

        if (mediaSession) {
            const seekBy = (offset) => {
                if (currentTrack) {
                    audio.currentTime = Math.max(0, Math.min(audio.duration || Infinity, audio.currentTime + offset));
                }
            };
            const handlers = {
                play: () => audio.play(),
                pause: () => audio.pause(),
                previoustrack: () => prevTrack && playTrack(prevTrack),
                nexttrack: () => nextTrack && playTrack(nextTrack),
                seekbackward: (details) => seekBy(-(details.seekOffset || 15)),
                seekforward: (details) => seekBy(details.seekOffset || 30),
                seekto: (details) => {
                    if (currentTrack && Number.isFinite(details.seekTime)) {
                        audio.currentTime = details.seekTime;
                    }
                },
            };

            Object.entries(handlers).forEach(([action, handler]) => {
                try {
                    mediaSession.setActionHandler(action, handler);
                } catch (e) {
                    // Action not supported by this browser
                }
            });

            audio.addEventListener('play', () => {
                mediaSession.playbackState = 'playing';
                updateMediaSession();
            });
            audio.addEventListener('pause', () => {
                mediaSession.playbackState = 'paused';
            });
            audio.addEventListener('loadedmetadata', updatePositionState);
            audio.addEventListener('seeked', updatePositionState);
            audio.addEventListener('ratechange', updatePositionState);
        }

        // Keyboard shortcuts, only when the visitor is not typing or using a control that already handles the key
        document.addEventListener('keydown', (e) => {
            if (!currentTrack || e.defaultPrevented || e.ctrlKey || e.altKey || e.metaKey) {
                return;
            }

            const target = e.target;

            if (target instanceof Element && target.closest('input, textarea, select, [contenteditable="true"]')) {
                return;
            }

            // Space / Enter press the focused button or link themselves
            const onControl = target instanceof Element && !!target.closest('button, a, summary, [role="button"]');
            const seekBy = (offset) => {
                audio.currentTime = Math.max(0, Math.min(audio.duration || Infinity, audio.currentTime + offset));
            };
            const setVolume = (value) => {
                audio.volume = Math.max(0, Math.min(1, Math.round(value * 100) / 100));
                volumeBar.value = audio.volume;
            };
            let handled = true;

            if (e.shiftKey) {
                switch (e.key) {
                    case 'N':
                        nextTrack && playTrack(nextTrack);
                        break;
                    case 'P':
                        prevTrack && playTrack(prevTrack);
                        break;
                    default:
                        handled = false;
                }
            } else if (/^[0-9]$/.test(e.key)) {
                if (Number.isFinite(audio.duration)) {
                    audio.currentTime = audio.duration * (Number(e.key) / 10);
                }
            } else {
                switch (e.key) {
                    case ' ':
                        if (onControl) {
                            return;
                        }
                    // falls through
                    case 'k':
                    case 'K':
                        playButton.click();
                        break;
                    case 'j':
                    case 'J':
                        seekBy(-10);
                        break;
                    case 'l':
                    case 'L':
                        seekBy(10);
                        break;
                    case 'ArrowLeft':
                        seekBy(-5);
                        break;
                    case 'ArrowRight':
                        seekBy(5);
                        break;
                    case 'ArrowUp':
                        setVolume(audio.volume + 0.05);
                        break;
                    case 'ArrowDown':
                        setVolume(audio.volume - 0.05);
                        break;
                    case 'm':
                    case 'M':
                        muteButton.click();
                        break;
                    default:
                        handled = false;
                }
            }

            if (handled) {
                e.preventDefault();
            }
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

        return {
            /**
             * The sessions of the server arrive after the player is ready: they only fill in the tracks
             * that have no progress kept in the browser and that the visitor did not start meanwhile.
             */
            applySession(id, session) {
                const track = findTrack(id);

                if (!session || !track || track === currentTrack || storedIds.has(track.id)) {
                    return;
                }

                track.currentTime = session.currentTime;
                track.volume = session.volume;
                track.complete = !!session.complete;

                const data = localData.find((t) => t.id === track.id);

                if (data) {
                    Object.assign(data, { currentTime: track.currentTime, volume: track.volume, complete: track.complete });
                }
            },
        };
    };

    const init = () => {
        initFilters();

        // Each module renders a player bar: only the first one is kept, all the tracks of the page share it
        const [playerEl, ...duplicates] = document.querySelectorAll('[data-audiotracks-player]');
        duplicates.forEach((el) => el.remove());

        if (playerEl) {
            loadState(playerEl, initPlayer(playerEl));
        }
    };

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
