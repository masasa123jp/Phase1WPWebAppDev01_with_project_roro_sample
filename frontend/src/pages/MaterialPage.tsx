import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface MaterialItem {
  id: number;
  name: string;
  price: string;
}

export default function MaterialPage() {
  const { t } = useTranslation();
  const [items, setItems] = useState<MaterialItem[]>([]);
  const [category, setCategory] = useState('A');
  const [species, setSpecies] = useState('both');
  useEffect(() => {
    fetch(`/wp-json/roro/v1/facilities?species=${species}&category=${category}`)
      .then((res) => res.json())
      .then((data) => setItems(data.materials));
  }, [category, species]);
  return (
    <div>
      <h1>{t('Material List')}</h1>
      <div>
        <label>
          {t('Category')}: <input value={category} onChange={(e) => setCategory(e.target.value)} />
        </label>
        <label>
          {t('Target species')}:
          <select value={species} onChange={(e) => setSpecies(e.target.value)}>
            <option value="dog">{t('Dog')}</option>
            <option value="cat">{t('Cat')}</option>
            <option value="both">{t('Both')}</option>
          </select>
        </label>
      </div>
      <ul>
        {items.map((it) => (
          <li key={it.id}>
            {it.name} - ¥{parseFloat(it.price).toLocaleString()}
          </li>
        ))}
      </ul>
    </div>
  );
}
