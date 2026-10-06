import { useEffect, useState } from 'react';

export function useMediaQuery(query) {
  const [matches, setMatches] = useState(() => window.matchMedia(query).matches);

  useEffect(() => {
    const media = window.matchMedia(query);
    const onChange = (event) => setMatches(event.matches);
    media.addEventListener('change', onChange);
    setMatches(media.matches);
    return () => media.removeEventListener('change', onChange);
  }, [query]);

  return matches;
}
