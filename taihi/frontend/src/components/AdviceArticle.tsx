import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface AdviceDetail {
  advice_code: string;
  title: string;
  body: string;
}

export default function AdviceArticle({ code }: { code: string }) {
  const { t } = useTranslation();
  const [article, setArticle] = useState<AdviceDetail | null>(null);
  useEffect(() => {
    fetch(`/wp-json/roro/v1/advice/${code}`)
      .then((res) => res.json())
      .then((data) => setArticle(data));
  }, [code]);
  if (!article) {
    return <p>{t('Loading...')}</p>;
  }
  return (
    <div>
      <h1>{article.title}</h1>
      <div dangerouslySetInnerHTML={{ __html: article.body }} />
    </div>
  );
}
