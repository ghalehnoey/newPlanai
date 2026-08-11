import os

import base64
import mimetypes
from openai import OpenAI

API_KEY = os.environ.get("OPENAI_API_KEY", "")
IMAGE_PATH = "22.png"

models = [
    "gpt-5.2-chat-latest",
    "gpt-5.3-chat-latest",
    "gapgpt-qwen-3.6",
    "gapgpt-qwen-3.6-thinking",
    "gemini-2.5-pro",
    "claude-sonnet-4-20250514"
]

# خواندن عکس
with open(IMAGE_PATH, "rb") as f:
    img = f.read()

image_base64 = base64.b64encode(img).decode()
mime_type, _ = mimetypes.guess_type(IMAGE_PATH)

client = OpenAI(
    base_url="https://api.gapgpt.app/v1",
    api_key=API_KEY
)

for model in models:
    print("\n========================")
    print("Testing model:", model)

    try:
        response = client.chat.completions.create(
            model=model,
            messages=[
                {
                    "role": "user",
                    "content": [
                        {
                            "type": "text",
                            "text": "و جواب بدهمتن داخل این تصویر را استخراج کن."
                        },
                        {
                            "type": "image_url",
                            "image_url": {
                                "url": f"data:{mime_type};base64,{image_base64}"
                            }
                        }
                    ]
                }
            ],
            max_tokens=300
        )

        print("✅ SUCCESS")
        print(response.choices[0].message.content)

    except Exception as e:
        print("❌ FAILED:", str(e))
