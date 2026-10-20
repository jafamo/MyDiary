(function () {
  // Sustituye el control nativo de cada <audio preload="none"> por el reproductor del tema.
  // Sin este script se queda el <audio controls> nativo.
  var players = document.querySelectorAll("[data-player]");

  function format(seconds) {
    var s = Math.max(0, Math.floor(seconds));
    return Math.floor(s / 60) + ":" + String(s % 60).padStart(2, "0");
  }

  players.forEach(function (player) {
    var audio = player.querySelector("audio");
    var toggle = player.querySelector(".player__toggle");
    var track = player.querySelector(".player__track");
    var progress = player.querySelector(".player__progress");
    var time = player.querySelector(".player__time");
    var current = player.querySelector(".player__current");
    var duration = parseFloat(player.getAttribute("data-duration")) || 0;

    audio.removeAttribute("controls");
    toggle.hidden = false;
    track.hidden = false;
    time.hidden = false;

    function setPlaying(playing) {
      player.classList.toggle("is-playing", playing);
      toggle.setAttribute("aria-label", toggle.getAttribute(playing ? "data-label-pause" : "data-label-play"));
    }

    toggle.addEventListener("click", function () {
      if (audio.paused) {
        // Un solo audio sonando a la vez.
        document.querySelectorAll("[data-player] audio").forEach(function (other) {
          if (other !== audio) {
            other.pause();
          }
        });
        audio.play();
      } else {
        audio.pause();
      }
    });

    audio.addEventListener("play", function () { setPlaying(true); });
    audio.addEventListener("pause", function () { setPlaying(false); });
    audio.addEventListener("ended", function () { setPlaying(false); });
    audio.addEventListener("timeupdate", function () {
      var total = audio.duration && isFinite(audio.duration) ? audio.duration : duration;
      current.textContent = format(audio.currentTime);
      progress.style.width = total ? (audio.currentTime / total) * 100 + "%" : "0";
    });
  });
})();
