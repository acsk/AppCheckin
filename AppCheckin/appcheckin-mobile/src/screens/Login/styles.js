import { StyleSheet } from 'react-native';

export default StyleSheet.create({
  bg: { flex: 1 },
  safe: { flex: 1 },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.35)',
  },
  container: {
    flex: 1,
    paddingHorizontal: 24,
    justifyContent: 'center',
  },
  header: {
    alignItems: 'center',
    marginBottom: 22,
  },
  logoImg: {
    width: 210,
    height: 100,
    resizeMode: 'contain',
  },
  title: {
    color: '#fff',
    fontSize: 24,
    fontWeight: '800',
    textAlign: 'center',
    marginBottom: 20,
  },
  form: {
    paddingTop: 4,
  },
  inputWrap: {
    height: 56,
    borderRadius: 14,
    paddingHorizontal: 14,
    gap: 10,
    marginBottom: 12,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(0,0,0,0.35)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.08)',
  },
  input: {
    flex: 1,
    color: '#fff',
    fontSize: 16,
  },
  btnWrap: {
    marginTop: 14,
    borderRadius: 18,
    overflow: 'hidden',
  },
  btnDisabled: {
    opacity: 0.55,
  },
  btn: {
    height: 54,
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '800',
    letterSpacing: 1,
  },
  link: {
    color: 'rgba(255,255,255,0.85)',
    textAlign: 'center',
    marginTop: 14,
    fontSize: 14,
    textDecorationLine: 'underline',
  },
  dividerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginTop: 20,
  },
  dividerLine: {
    flex: 1,
    height: 1,
    backgroundColor: 'rgba(255,255,255,0.12)',
  },
  dividerText: {
    color: 'rgba(255,255,255,0.65)',
    fontSize: 13,
  },
  socialRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 16,
    marginTop: 18,
  },
  socialBtn: {
    width: 60,
    height: 60,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
  },
  footer: {
    marginTop: 18,
    flexDirection: 'row',
    justifyContent: 'center',
  },
  footerText: {
    color: 'rgba(255,255,255,0.85)',
    fontSize: 14,
  },
  footerLink: {
    color: '#fff',
    fontWeight: '800',
    textDecorationLine: 'underline',
  },
  mensagem: {
    color: '#e2e8f0',
    textAlign: 'center',
    marginTop: 10,
  },
});
