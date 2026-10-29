#!/usr/bin/env python3
# Worker que baixa o áudio de links do YouTube que estão na fila do Lectta e
# entrega pro pipeline do app (api/youtube/ingest) -- dali em
# diante uma aula de YouTube é indistinguível de um upload manual de mp3.
#
# Roda via cron (não fica de pé feito daemon): cada execução processa até
# MAX_JOBS_PER_RUN aulas da fila e sai. A hospedagem não tem ffmpeg/yt-dlp
# no PHP, então isso roda à parte, em Python, via SSH -- ver
# worker/config.py.example para as instruções de instalação.

import glob
import os
import shutil
import sys
import tempfile
import traceback

import requests
import yt_dlp

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import config

# O cron roda com um PATH mínimo, sem ~/bin -- que é onde ficam o ffmpeg e o
# deno (runtime JS que o yt-dlp usa pra resolver o desafio "n" do YouTube).
# yt-dlp procura o deno no PATH, não tem opção equivalente ao
# ffmpeg_location pra apontar direto, então precisa entrar aqui.
os.environ['PATH'] = config.FFMPEG_LOCATION + os.pathsep + os.environ.get('PATH', '')


def fetch_next_job():
    resp = requests.get(
        f"{config.BASE_URL}/api/youtube/jobs",
        params={"token": config.API_TOKEN},
        timeout=config.REQUEST_TIMEOUT,
    )
    resp.raise_for_status()
    return resp.json().get("job")


def report_error(class_id: str, message: str) -> None:
    try:
        requests.post(
            f"{config.BASE_URL}/api/youtube/ingest",
            params={"token": config.API_TOKEN},
            data={"id": class_id, "error": message[:500]},
            timeout=config.REQUEST_TIMEOUT,
        )
    except requests.RequestException as exc:
        print(f"[job {class_id}] falha ao reportar erro pro servidor: {exc}")


def report_success(class_id: str, mp3_path: str) -> None:
    with open(mp3_path, "rb") as fh:
        files = {"audio": (os.path.basename(mp3_path), fh, "audio/mpeg")}
        resp = requests.post(
            f"{config.BASE_URL}/api/youtube/ingest",
            params={"token": config.API_TOKEN},
            data={"id": class_id},
            files=files,
            timeout=180,
        )
    resp.raise_for_status()
    body = resp.json()
    if not body.get("ok"):
        raise RuntimeError(f"servidor recusou o áudio: {body}")


def download_audio(youtube_url: str, workdir: str) -> str:
    ydl_opts = {
        "format": "bestaudio/best",
        "outtmpl": os.path.join(workdir, "%(id)s.%(ext)s"),
        "ffmpeg_location": config.FFMPEG_LOCATION,
        # Permite o yt-dlp baixar (do GitHub oficial do projeto) o script que
        # resolve o desafio "n" do YouTube. Sem isso, só formatos de imagem
        # ficam disponíveis -- é o equivalente à flag de CLI
        # --remote-components ejs:github. Ainda precisa de um runtime JS
        # (deno) instalado em ~/bin pra rodar esse script, daí o PATH acima.
        "remote_components": ["ejs:github"],
        "postprocessors": [{
            "key": "FFmpegExtractAudio",
            "preferredcodec": "mp3",
            "preferredquality": str(config.AUDIO_BITRATE_KBPS),
        }],
        "noplaylist": True,
        "quiet": True,
        "no_warnings": False,
        "socket_timeout": 30,
    }
    if getattr(config, "COOKIES_FILE", None):
        ydl_opts["cookiefile"] = config.COOKIES_FILE

    with yt_dlp.YoutubeDL(ydl_opts) as ydl:
        info = ydl.extract_info(youtube_url, download=False)
        if info.get("is_live"):
            raise RuntimeError("é uma live em andamento, não um vídeo -- não dá pra baixar")
        duration = info.get("duration") or 0
        if duration and duration > config.MAX_DURATION_SECONDS:
            raise RuntimeError(
                f"vídeo tem {duration / 3600:.1f}h, acima do limite de "
                f"{config.MAX_DURATION_SECONDS / 3600:.1f}h configurado em config.py"
            )

        ydl.download([youtube_url])

    mp3_files = glob.glob(os.path.join(workdir, "*.mp3"))
    if not mp3_files:
        raise RuntimeError("yt-dlp/ffmpeg não geraram um .mp3 (formato de áudio incomum?)")
    return mp3_files[0]


def process_job(job: dict) -> None:
    class_id = str(job["id"])
    youtube_url = job["youtube_url"]
    print(f"[job {class_id}] baixando {youtube_url}")

    workdir = tempfile.mkdtemp(prefix=f"ytjob{class_id}_")
    try:
        mp3_path = download_audio(youtube_url, workdir)
        size_mb = os.path.getsize(mp3_path) / 1024 / 1024
        print(f"[job {class_id}] baixado ({size_mb:.1f} MB), enviando pro servidor")
        report_success(class_id, mp3_path)
        print(f"[job {class_id}] ok")
    except Exception as exc:
        print(f"[job {class_id}] erro: {exc}")
        traceback.print_exc()
        report_error(class_id, str(exc))
    finally:
        shutil.rmtree(workdir, ignore_errors=True)


def main() -> None:
    for _ in range(config.MAX_JOBS_PER_RUN):
        job = fetch_next_job()
        if not job:
            break
        process_job(job)


if __name__ == "__main__":
    main()
