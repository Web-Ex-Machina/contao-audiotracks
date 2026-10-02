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
     * POST to the bundle ajax route, answers with JSON.
     */
    const post = async (player, action, data) => {
        const body = new URLSearchParams({
            TL_AJAX: '1',
            REQUEST_TOKEN: player.dataset.requestToken,
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

    const initPlayer = (playerEl) => {
        const playButtons = [...document.querySelectorAll('.audiotrack__play')];

        const trackList = playButtons.map((button) => ({
            id: button.dataset.id,
            title: button.dataset.title,
            src: button.dataset.src,
            currentTime: parseFloat(button.dataset.currentTime) || 0,
            volume: button.dataset.volume ? parseFloat(button.dataset.volume) : 1,
            complete: '1' === button.dataset.complete,
        }));

        const findTrack = (id) => trackList.find((t) => t.id === String(id));
        const listenedEl = (id) => document.querySelector(`.audiotrack[data-audiotrack="${id}"] .audiotrack__listened`);

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
            initPlayer(playerEl);
        }
    };

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
