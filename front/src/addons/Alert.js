import { Dialog, Notify } from 'quasar'
import Icon from '../components/Icon.vue'

export class Alert {
  static warning (message, subTitle = '') {
    Notify.create({
      progress: true,
      color: 'white',
      textColor: 'black',
      position: 'top',
      message,
      caption: subTitle,
      timeout: 3000,
      icon: 'warning',
      iconColor: 'warning',
      actions: [
        { icon: 'close', color: 'black', round: true, size: 'xs' }
      ],
      classes: 'text-bold'
    })
  }

  static info (message, subTitle = '') {
    Notify.create({
      progress: true,
      color: 'white',
      textColor: 'black',
      position: 'top',
      message,
      caption: subTitle,
      timeout: 3000,
      icon: 'info',
      iconColor: 'info',
      actions: [
        { icon: 'close', color: 'black', round: true, size: 'xs' }
      ],
      classes: 'text-bold'
    })
  }

  static success (message, subTitle = '') {
    Notify.create({
      progress: true,
      color: 'white',
      textColor: 'black',
      position: 'top',
      message,
      caption: subTitle,
      timeout: 3000,
      icon: 'check_circle',
      iconColor: 'positive',
      actions: [
        { icon: 'close', color: 'black', round: true, size: 'xs' }
      ],
      classes: 'text-bold'
    })
  }

  static error (message, subTitle = '') {
    Notify.create({
      progress: true,
      color: 'white',
      textColor: 'black',
      position: 'top',
      message,
      caption: subTitle,
      timeout: 3000,
      icon: 'error',
      iconColor: 'negative',
      actions: [
        { icon: 'close', color: 'black', round: true, size: 'xs' }
      ],
      classes: 'text-bold'
    })
  }

  static dialog (title, message) {
    return Dialog.create({
      component: Icon,
      componentProps: {
        title,
        message,
        icon: 'warning',
        color: 'negative',
      }
    })
  }

  static confirm (message) {
    return Dialog.create({
      title: 'Confirmación',
      message,
      color: 'positive',
      ok: {
        label: 'Aceptar',
        color: 'positive'
      },
      cancel: {
        label: 'Cancelar',
        color: 'negative'
      },
    })
  }

  static dialogPrompt (message) {
    return Dialog.create({
      title: 'Confirmación',
      message,
      color: 'positive',
      ok: {
        label: 'Aceptar',
        color: 'positive'
      },
      cancel: {
        label: 'Cancelar',
        color: 'negative'
      },
      prompt: {
        model: '',
        type: 'text',
        label: 'Ingrese el texto',
      }
    })
  }
}
