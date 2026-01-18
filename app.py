from flask import Flask, request, jsonify, send_from_directory
from flask_cors import CORS
import subprocess
import os
import uuid
import re
import shutil
import sys
import glob
import yt_dlp

app = Flask(__name__, static_folder='.')
CORS(app)

# Configuration
OUTPUT_DIR = os.path.join(os.path.dirname(__file__), 'gifs')
TEMP_DIR = os.path.join(os.path.dirname(__file__), 'temp')

# Create directories
os.makedirs(OUTPUT_DIR, exist_ok=True)
os.makedirs(TEMP_DIR, exist_ok=True)

def extract_video_id(url):
    """Extract YouTube video ID from various URL formats"""
    patterns = [
        r'(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/v\/|youtube\.com\/shorts\/)([^&\n?#]+)',
        r'^([a-zA-Z0-9_-]{11})$'
    ]
    
    for pattern in patterns:
        match = re.search(pattern, url)
        if match:
            return match.group(1)
    return None

def format_bytes(size):
    """Format bytes to human readable string"""
    for unit in ['B', 'KB', 'MB', 'GB']:
        if size < 1024:
            return f"{size:.2f} {unit}"
        size /= 1024
    return f"{size:.2f} GB"

@app.route('/')
def index():
    return send_from_directory('.', 'youtube-to-gif.html')

@app.route('/<path:filename>')
def serve_static(filename):
    return send_from_directory('.', filename)

@app.route('/gifs/<path:filename>')
def serve_gif(filename):
    return send_from_directory(OUTPUT_DIR, filename)

@app.route('/convert', methods=['POST'])
def convert():
    try:
        data = request.get_json()
        
        url = data.get('url', '')
        start_time = float(data.get('startTime', 0))
        end_time = float(data.get('endTime', 5))
        fps = int(data.get('fps', 15))
        width = int(data.get('width', 480))
        quality = data.get('quality', 'medium')
        
        # Validate YouTube URL
        video_id = extract_video_id(url)
        if not video_id:
            return jsonify({'success': False, 'error': 'Invalid YouTube URL'})
        
        # Validate duration
        duration = end_time - start_time
        if duration <= 0 or duration > 30:
            return jsonify({'success': False, 'error': 'Duration must be between 0 and 30 seconds'})
        
        # Check for ffmpeg
        ffmpeg_path = shutil.which('ffmpeg')
        if not ffmpeg_path:
            return jsonify({
                'success': False,
                'error': 'ffmpeg is not installed. Install with: brew install ffmpeg'
            })
        
        # Quality settings
        quality_settings = {
            'low': {'scale': min(width, 320), 'colors': 64},
            'medium': {'scale': min(width, 480), 'colors': 128},
            'high': {'scale': min(width, 640), 'colors': 256}
        }
        settings = quality_settings.get(quality, quality_settings['medium'])
        
        # Generate unique filenames
        unique_id = str(uuid.uuid4())[:8]
        video_file = os.path.join(TEMP_DIR, f'{unique_id}.mp4')
        gif_file = os.path.join(OUTPUT_DIR, f'{unique_id}.gif')
        palette_file = os.path.join(TEMP_DIR, f'{unique_id}_palette.png')
        
        youtube_url = f'https://www.youtube.com/watch?v={video_id}'
        full_video_base = os.path.join(TEMP_DIR, f'{unique_id}_full')
        
        # Download video using yt-dlp Python library
        ydl_opts = {
            'format': 'bv*[height<=720]+ba/b[height<=720]/bv*+ba/b',
            'outtmpl': full_video_base + '.%(ext)s',
            'quiet': False,
            'no_warnings': False,
            'extractor_args': {'youtube': {'player_client': ['android', 'web']}},
            'merge_output_format': 'mp4',
        }
        
        try:
            with yt_dlp.YoutubeDL(ydl_opts) as ydl:
                ydl.download([youtube_url])
        except Exception as download_error:
            return jsonify({
                'success': False,
                'error': f'Failed to download video: {str(download_error)}'
            })
        
        # Find the downloaded file (extension may vary)
        downloaded_files = glob.glob(full_video_base + '.*')
        if not downloaded_files:
            return jsonify({'success': False, 'error': 'Failed to download video - no file found'})
        
        full_video_file = downloaded_files[0]
        
        # Trim with ffmpeg to get the segment we need
        trim_cmd = [
            ffmpeg_path, '-y',
            '-ss', str(start_time),
            '-i', full_video_file,
            '-t', str(duration),
            '-c:v', 'libx264',
            '-c:a', 'aac',
            video_file
        ]
        
        result = subprocess.run(trim_cmd, capture_output=True, text=True)
        
        # Clean up full video
        if os.path.exists(full_video_file):
            os.remove(full_video_file)
        
        if not os.path.exists(video_file):
            return jsonify({'success': False, 'error': f'Failed to trim video: {result.stderr}'})
        
        # Generate palette for better GIF quality
        palette_cmd = [
            ffmpeg_path, '-y',
            '-i', video_file,
            '-vf', f"fps={fps},scale={settings['scale']}:-1:flags=lanczos,palettegen=max_colors={settings['colors']}",
            palette_file
        ]
        
        result = subprocess.run(palette_cmd, capture_output=True, text=True)
        
        if not os.path.exists(palette_file):
            return jsonify({'success': False, 'error': 'Failed to generate color palette'})
        
        # Convert to GIF using the palette
        gif_cmd = [
            ffmpeg_path, '-y',
            '-i', video_file,
            '-i', palette_file,
            '-lavfi', f"fps={fps},scale={settings['scale']}:-1:flags=lanczos[x];[x][1:v]paletteuse=dither=bayer:bayer_scale=5",
            gif_file
        ]
        
        result = subprocess.run(gif_cmd, capture_output=True, text=True)
        
        # Clean up temp files
        for f in [video_file, palette_file]:
            if os.path.exists(f):
                os.remove(f)
        
        if not os.path.exists(gif_file):
            return jsonify({'success': False, 'error': 'Failed to create GIF'})
        
        # Get file info
        file_size = os.path.getsize(gif_file)
        gif_url = f'gifs/{os.path.basename(gif_file)}'
        
        return jsonify({
            'success': True,
            'gifUrl': gif_url,
            'fileSize': file_size,
            'fileSizeFormatted': format_bytes(file_size)
        })
        
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

if __name__ == '__main__':
    print("YouTube to GIF Converter running at http://localhost:5000")
    print("Open http://localhost:5000 in your browser")
    app.run(debug=True, port=5000)
