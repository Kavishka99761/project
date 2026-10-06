import os
from functools import lru_cache

from fastapi import FastAPI
from pydantic import BaseModel, Field
from sentence_transformers import SentenceTransformer

MODEL_NAME = os.getenv(
    "SENTENCE_TRANSFORMER_MODEL",
    "sentence-transformers/all-MiniLM-L6-v2",
)

app = FastAPI(title="EDU-SMART Academic AI Embeddings")


class RankRequest(BaseModel):
    query: str = Field(min_length=1, max_length=2000)
    passages: list[str] = Field(min_length=1, max_length=512)


@lru_cache(maxsize=1)
def get_model() -> SentenceTransformer:
    return SentenceTransformer(MODEL_NAME)


@app.get("/health")
def health() -> dict[str, str]:
    get_model()
    return {"status": "ok", "model": MODEL_NAME}


@app.post("/rank")
def rank(request: RankRequest) -> dict[str, list[float]]:
    embeddings = get_model().encode(
        [request.query, *request.passages],
        normalize_embeddings=True,
        convert_to_numpy=True,
        show_progress_bar=False,
    )
    scores = embeddings[1:] @ embeddings[0]

    return {"scores": [float(score) for score in scores]}
