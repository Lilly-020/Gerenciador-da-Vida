import '../css/social.css';
import { createApp } from 'vue';
import SocialApp from './SocialApp.vue';

export function setupSocial(root) {
    createApp(SocialApp, {
        base: root.dataset.base,
        profileSettings: root.dataset.profileSettings,
        viewer: Number(root.dataset.viewer),
        person: Number(root.dataset.person) || null,
        section: root.dataset.section,
    }).mount(root);
}
