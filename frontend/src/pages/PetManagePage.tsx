import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Pet {
  pet_id: number;
  pet_name: string;
  species: string;
  breed_id: number;
  age_group: string;
  category: string;
}

export default function PetManagePage() {
  const { t } = useTranslation();
  const [pets, setPets] = useState<Pet[]>([]);
  const [form, setForm] = useState({ pet_name: '', species: 'dog', breed_id: 0, age_group: 'puppy', category: 'A' });
  useEffect(() => {
    fetch('/wp-json/roro/v1/customer/pets')
      .then((res) => res.json())
      .then((data) => setPets(data));
  }, []);
  const addPet = () => {
    fetch('/wp-json/roro/v1/customer/pets', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(form),
    })
      .then((res) => res.json())
      .then((data) => setPets([...pets, { pet_id: data.pet_id, ...form }]))
      .then(() => setForm({ pet_name: '', species: 'dog', breed_id: 0, age_group: 'puppy', category: 'A' }));
  };
  return (
    <div>
      <h1>{t('Pet Management')}</h1>
      <ul>
        {pets.map((p) => (
          <li key={p.pet_id}>
            {p.pet_name} ({t(p.species)}) - {t('Category')} {p.category}
          </li>
        ))}
      </ul>
      <h2>{t('Add New Pet')}</h2>
      <input
        placeholder={t('Name')}
        value={form.pet_name}
        onChange={(e) => setForm({ ...form, pet_name: e.target.value })}
      />
      <select value={form.species} onChange={(e) => setForm({ ...form, species: e.target.value })}>
        <option value="dog">{t('Dog')}</option>
        <option value="cat">{t('Cat')}</option>
      </select>
      <input
        placeholder={t('Breed ID')}
        type="number"
        value={form.breed_id}
        onChange={(e) => setForm({ ...form, breed_id: Number(e.target.value) })}
      />
      <select value={form.age_group} onChange={(e) => setForm({ ...form, age_group: e.target.value })}>
        <option value="puppy">{t('Puppy/Kitten')}</option>
        <option value="adult">{t('Adult')}</option>
        <option value="senior">{t('Senior')}</option>
      </select>
      <input
        placeholder={t('Category')}
        value={form.category}
        onChange={(e) => setForm({ ...form, category: e.target.value })}
      />
      <button onClick={addPet}>{t('Add')}</button>
    </div>
  );
}
