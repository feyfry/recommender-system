import './bootstrap';
import Alpine from 'alpinejs';
import Web3 from 'web3';
import {ethers} from 'ethers';
import '@fortawesome/fontawesome-free/css/all.min.css';

window.Alpine = Alpine;
window.Web3 = Web3;
window.ethers = ethers;

Alpine.start();
