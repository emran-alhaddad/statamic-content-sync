import { createApp } from 'vue';
import Utility from './components/Utility.vue';

Statamic.booting(() => {
  Statamic.$components.register('content-sync-utility', Utility);
});
