import { describe, it, expect, vi } from 'vitest';
import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';
import { searchFacilities } from '../../frontend/src/api/facilities';

describe('searchFacilities', () => {
  it('returns array', async () => {
    const mock = new MockAdapter(axios);
    mock.onGet(/facility-search/).reply(200, [{ id: 1, name: 'Foo', genre: 1, dist: 111 }]);
    const data = await searchFacilities(0, 0);
    expect(data).toHaveLength(1);
  });
});
