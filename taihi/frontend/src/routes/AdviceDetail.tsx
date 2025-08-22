import React, { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import axios from 'axios';

interface Advice {
  id: number;
  title: { rendered: string };
  content: { rendered: string };
}

export default function AdviceDetail() {
  const { id } = useParams<{ id: string }>();
  const [advice, setAdvice] = useState<Advice | null>(null);

  useEffect(() => {
    (async () => {
      const { data } = await axios.get<Advice>(`/wp-json/wp/v2/roro_advice/${id}`);
      setAdvice(data);
    })();
  }, [id]);

  if (!advice) return <p>Loading…</p>;

  return (
    <article>
      <h1 dangerouslySetInnerHTML={{ __html: advice.title.rendered }} />
      <div dangerouslySetInnerHTML={{ __html: advice.content.rendered }} />
    </article>
  );
}
