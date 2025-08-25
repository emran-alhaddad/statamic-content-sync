import './styles/content-sync.css'; 
import Utility from './components/Utility.vue';

Statamic.booting(() => {
  Statamic.$components.register('content-sync-utility', Utility);
});
